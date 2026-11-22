<?php

namespace Tests\Feature;

use App\Console\Commands\ClearPrelaunchData;
use App\Models\{Booking, Room, User};
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, File, Schema};
use PDO;
use Tests\TestCase;

class ClearPrelaunchDataTest extends TestCase
{
    use RefreshDatabase;

    private string $sandbox;
    private string $originalStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalStorage = storage_path();
        $this->sandbox = storage_path('framework/testing/prelaunch-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($this->sandbox);
        $this->app->useStoragePath($this->sandbox);
        $this->mock(MaintenanceMode::class, fn ($mock) => $mock->shouldReceive('active')->andReturn(true));
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStorage);
        File::deleteDirectory($this->sandbox);
        parent::tearDown();
    }

    private function fixture(): array
    {
        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $room = Room::create(['name' => "Owner's room", 'type' => 'Suite', 'price_per_night' => '80625.00',
            'images' => ['rooms/retained.jpg'], 'status' => 'occupied', 'is_available' => false,
            'discount_enabled' => true, 'discount_percentage' => 25, 'discount_label' => 'Opening Rate']);
        $stay = Booking::create(['user_id' => $owner->id, 'room_id' => $room->id, 'check_in' => '2026-12-01',
            'check_out' => '2026-12-02', 'total_price' => '80625.00', 'payment_method' => 'pos']);
        DB::table('assisted_reservation_payments')->insert(['booking_id' => $stay->id, 'submitted_by' => $owner->id,
            'amount_kobo' => 8062500, 'reference' => 'TEST-POS', 'transaction_date' => '2026-11-22', 'proof_path' => 'private/test.jpg']);
        $product = DB::table('products')->insertGetId(['name' => 'Retain product', 'price' => 1500, 'category' => 'art', 'stock' => 7]);
        DB::table('orders')->insert(['user_id' => $owner->id, 'product_id' => $product, 'quantity' => 1, 'total_price' => 1500, 'payment_method' => 'pos']);
        DB::table('manual_bank_accounts')->insert(['bank_name' => 'Retained bank', 'account_number' => '1234567890', 'account_name' => 'Insignia']);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
        File::ensureDirectoryExists(storage_path('app/public/rooms'));
        file_put_contents(storage_path('app/public/rooms/retained.jpg'), 'synthetic image bytes');
        return [$owner, $room, $stay];
    }

    public function test_preview_never_modifies_records_or_creates_backups(): void
    {
        [, $room] = $this->fixture();
        $this->artisan('launch:clear-test-data')->expectsOutputToContain('PREVIEW ONLY')->assertSuccessful();
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame('occupied', $room->fresh()->status);
        $this->assertDirectoryDoesNotExist(storage_path('app/private/prelaunch-backups'));
    }

    public function test_execution_requires_maintenance_and_stopped_workers(): void
    {
        $this->fixture();
        $this->artisan('launch:clear-test-data', ['--execute' => true])->assertFailed();
        $this->mock(MaintenanceMode::class, fn ($mock) => $mock->shouldReceive('active')->andReturn(false));
        $this->artisan('launch:clear-test-data', ['--execute' => true, '--workers-stopped' => true])->assertFailed();
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('assisted_reservation_payments', 1);
    }

    public function test_cleanup_preserves_setup_and_media_and_writes_a_restorable_backup(): void
    {
        [$owner, $room, $stay] = $this->fixture();
        $roomBefore = DB::table('rooms')->first();
        $this->artisan('launch:clear-test-data', ['--execute' => true, '--workers-stopped' => true])->assertSuccessful();
        foreach (ClearPrelaunchData::TABLES as $table) $this->assertDatabaseCount($table, 0);
        $this->assertEquals($roomBefore, DB::table('rooms')->first());
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'email' => $owner->email]);
        $this->assertDatabaseHas('products', ['name' => 'Retain product', 'stock' => 7]);
        $this->assertDatabaseHas('manual_bank_accounts', ['account_name' => 'Insignia']);
        $this->assertDatabaseCount('reservation_payment_policy', 1);
        $this->assertFileExists(storage_path('app/public/rooms/retained.jpg'));
        $files = glob(storage_path('app/private/prelaunch-backups/*.sql'));
        $this->assertCount(1, $files);
        $sql = file_get_contents($files[0]);
        $this->assertStringEndsWith("-- BACKUP COMPLETE\n", $sql);
        // Restore the archive into a separate, isolated copy of the same schema.
        $copy = new PDO('sqlite::memory:');
        foreach (DB::select("SELECT sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'") as $table) $copy->exec($table->sql);
        $copy->exec($sql);
        $this->assertEquals(1, $copy->query('SELECT COUNT(*) FROM bookings')->fetchColumn());
        $this->assertEquals(1, $copy->query('SELECT COUNT(*) FROM assisted_reservation_payments')->fetchColumn());
        $this->assertEquals('80625', $copy->query('SELECT price_per_night FROM rooms')->fetchColumn());
        // Do not reuse old payable IDs after clearing test gateway references.
        $new = Booking::create(['user_id' => $owner->id, 'room_id' => $room->id, 'check_in' => '2026-12-02',
            'check_out' => '2026-12-03', 'total_price' => 80625, 'payment_method' => 'pos']);
        $this->assertGreaterThan($stay->id, $new->id);
    }

    public function test_explicit_room_reset_preserves_prices_photos_and_availability_switch(): void
    {
        [, $room] = $this->fixture();
        $this->artisan('launch:clear-test-data', ['--execute' => true, '--workers-stopped' => true, '--rooms-ready' => true])->assertSuccessful();
        $room->refresh();
        $this->assertSame('available', $room->status);
        $this->assertFalse($room->is_available);
        $this->assertEquals(80625, $room->price_per_night);
        $this->assertTrue($room->discount_enabled);
        $this->assertSame(['rooms/retained.jpg'], json_decode($room->getRawOriginal('images'), true));
    }

    public function test_foreign_key_failure_rolls_back_every_deletion_and_keeps_the_backup(): void
    {
        [, , $stay] = $this->fixture();
        Schema::create('future_protected_record', function (Blueprint $table) {
            $table->id(); $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
        });
        try {
            DB::table('future_protected_record')->insert(['booking_id' => $stay->id]);
            $this->artisan('launch:clear-test-data', ['--execute' => true, '--workers-stopped' => true])->assertFailed();
            $this->assertDatabaseCount('bookings', 1);
            $this->assertDatabaseCount('assisted_reservation_payments', 1);
            $this->assertDatabaseCount('jobs', 1);
            $this->assertCount(1, glob(storage_path('app/private/prelaunch-backups/*.sql')));
        } finally {
            Schema::dropIfExists('future_protected_record');
        }
    }
}
