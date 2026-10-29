<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_default_seeding_creates_only_the_named_owner_without_demo_data(): void
    {
        $this->app->instance('env', 'production');
        $password = 'Test-Owner-Passphrase-42!';
        $this->artisan('db:seed', ['--force' => true])
            ->expectsQuestion('Owner name', 'Property Owner')
            ->expectsQuestion('Owner login email', 'owner@example.test')
            ->expectsQuestion('Owner password', $password)
            ->expectsQuestion('Confirm owner password', $password)
            ->assertSuccessful();

        $owner = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_IPL_ADMIN, $owner->role);
        $this->assertTrue(Hash::check($password, $owner->password));
        $this->assertDatabaseCount('users', 1);
        foreach (['rooms', 'bookings', 'products', 'services', 'accommodation_ledgers', 'expenses', 'petty_cash_transactions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_rerunning_does_not_reset_an_existing_owner_password_or_name(): void
    {
        $owner = User::factory()->create(['email' => 'existing@example.test', 'name' => 'Existing owner', 'role' => User::ROLE_IPL_ADMIN]);
        $originalPassword = $owner->password;
        $this->artisan('db:seed', ['--class' => ProductionSeeder::class, '--force' => true])
            ->expectsQuestion('Owner name', 'Replacement name')
            ->expectsQuestion('Owner login email', 'existing@example.test')
            ->assertSuccessful();
        $this->assertSame($originalPassword, $owner->fresh()->password);
        $this->assertSame('Existing owner', $owner->fresh()->name);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_password_confirmation_must_match_before_any_account_is_created(): void
    {
        try {
            $this->artisan('db:seed', ['--class' => ProductionSeeder::class, '--force' => true])
                ->expectsQuestion('Owner name', 'Owner')
                ->expectsQuestion('Owner login email', 'owner@example.test')
                ->expectsQuestion('Owner password', 'Test-Owner-Passphrase-42!')
                ->expectsQuestion('Confirm owner password', 'Different-Passphrase-42!')
                ->run();
            $this->fail('Expected password confirmation validation');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_guest_account_is_not_promoted_or_given_a_new_password(): void
    {
        $guest = User::factory()->create(['email' => 'guest@example.test', 'role' => User::ROLE_USER]);
        $password = $guest->password;
        try {
            $this->artisan('db:seed', ['--class' => ProductionSeeder::class, '--force' => true])
                ->expectsQuestion('Owner name', 'Owner')
                ->expectsQuestion('Owner login email', 'guest@example.test')
                ->run();
            $this->fail('Expected existing-account rejection');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('existing non-owner account', $exception->getMessage());
        }
        $this->assertSame(User::ROLE_USER, $guest->fresh()->role);
        $this->assertSame($password, $guest->fresh()->password);
        $this->assertDatabaseCount('users', 1);
    }
}
