<?php

namespace Tests\Feature;

use App\Filament\Pages\ManualBankSettings;
use App\Models\ManualBankAccount;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ManualBankAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_is_unavailable_until_ipl_owner_activates_it(): void
    {
        $guest = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($guest)->getJson('/api/payment-methods/manual-bank')
            ->assertOk()->assertJsonPath('is_configured', false)
            ->assertJsonPath('account_number', null);

        $account = ManualBankAccount::create([
            'bank_name' => 'Example Bank',
            'account_name' => 'Example Business Ltd',
            'account_number' => '0123456789',
            'is_active' => false,
            'updated_by' => $guest->id,
        ]);

        $this->getJson('/api/payment-methods/manual-bank')
            ->assertOk()->assertJsonPath('is_configured', false)
            ->assertJsonPath('account_number', null);

        $account->update(['is_active' => true]);

        $this->getJson('/api/payment-methods/manual-bank')
            ->assertOk()->assertJsonPath('is_configured', true)
            ->assertJsonPath('bank_name', 'Example Bank')
            ->assertJsonPath('account_number', '0123456789')
            ->assertJsonPath('account_name', 'Example Business Ltd');

        $account->update(['is_active' => false]);

        $this->getJson('/api/payment-methods/manual-bank')
            ->assertOk()->assertJsonPath('is_configured', false)
            ->assertJsonPath('account_number', null);
    }

    public function test_only_ipl_roles_can_access_owner_bank_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_STAFF]));
        $this->assertFalse(ManualBankSettings::canAccess());

        $this->actingAs(User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]));
        $this->assertFalse(ManualBankSettings::canAccess());

        $this->actingAs(User::factory()->create(['role' => User::ROLE_IPL_ADMIN]));
        $this->assertTrue(ManualBankSettings::canAccess());
    }

    public function test_manual_booking_receipt_is_rejected_when_bank_details_are_disabled(): void
    {
        $guest = User::factory()->create(['role' => User::ROLE_USER]);
        $room = Room::create([
            'name' => 'Transfer Suite', 'type' => 'Suite',
            'price_per_night' => 50000, 'status' => 'available', 'is_available' => true,
        ]);

        $this->actingAs($guest)->post('/api/bookings', [
            'room_id' => $room->id,
            'check_in' => today()->addDays(5)->toDateString(),
            'check_out' => today()->addDays(7)->toDateString(),
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_ipl_can_activate_replace_and_disable_account_without_exposing_previous_details(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $this->actingAs($owner);

        \Livewire\Livewire::test(ManualBankSettings::class)
            ->set('bankName', 'Example One Bank')
            ->set('accountName', 'Test Beneficiary Ltd')
            ->set('accountNumber', '0123456789')
            ->set('active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->getJson('/api/payment-methods/manual-bank')
            ->assertOk()->assertJsonPath('is_configured', true)
            ->assertJsonPath('account_number', '0123456789');

        \Livewire\Livewire::test(ManualBankSettings::class)
            ->set('bankName', 'Example Two Bank')
            ->set('accountName', 'Different Test Beneficiary')
            ->set('accountNumber', '9876543210')
            ->set('active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, ManualBankAccount::where('is_active', true)->count());
        $this->assertSame(2, ManualBankAccount::count());
        $this->getJson('/api/payment-methods/manual-bank')
            ->assertOk()->assertJsonPath('account_number', '9876543210')
            ->assertJsonPath('bank_name', 'Example Two Bank');

        \Livewire\Livewire::test(ManualBankSettings::class)
            ->set('active', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->getJson('/api/payment-methods/manual-bank')
            ->assertOk()->assertJsonPath('is_configured', false)
            ->assertJsonPath('account_number', null);
    }

    public function test_ipl_cannot_activate_malformed_account_number(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_IPL_ADMIN]));
        \Livewire\Livewire::test(ManualBankSettings::class)
            ->set('bankName', 'Example Bank')
            ->set('accountName', 'Test Business')
            ->set('accountNumber', '12345')
            ->set('active', true)
            ->call('save')
            ->assertHasErrors(['accountNumber']);
        $this->assertDatabaseCount('manual_bank_accounts', 0);
    }

    public function test_guest_bank_details_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/payment-methods/manual-bank')->assertUnauthorized();
    }
}
