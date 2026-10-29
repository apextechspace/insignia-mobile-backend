<?php

namespace Tests\Feature;

use App\Models\Room;
use Database\Seeders\RoomSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionRoomSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_room_setup_creates_only_the_six_rooms_without_demo_stays(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('db:seed', ['--class' => RoomSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('rooms', 6);
        foreach (['Atala', 'Junonia', 'Lyria', 'Evania', 'Morpho', 'Papilio'] as $name) {
            $this->assertDatabaseHas('rooms', ['name' => $name, 'status' => 'available', 'is_available' => true]);
        }
        $room = Room::where('name', 'Atala')->firstOrFail();
        $this->assertContains('rooms/atala_standard.jpg', $room->getRawOriginal('images') ? json_decode($room->getRawOriginal('images'), true) : []);
        foreach (['users', 'bookings', 'accommodation_ledgers', 'expenses', 'staff_tasks'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_reseeding_on_production_preserves_edited_prices_images_and_room_readiness(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('db:seed', ['--class' => RoomSeeder::class, '--force' => true])->assertSuccessful();
        $room = Room::where('name', 'Atala')->firstOrFail();
        $room->update([
            'price_per_night' => 90000, 'images' => ['rooms/live-edited-photo.jpg'],
            'description' => 'Edited on live', 'status' => 'maintenance', 'is_available' => false,
        ]);
        $this->artisan('db:seed', ['--class' => RoomSeeder::class, '--force' => true])->assertSuccessful();

        $room->refresh();
        $this->assertDatabaseCount('rooms', 6);
        $this->assertEquals(90000, $room->price_per_night);
        $this->assertSame('Edited on live', $room->description);
        $this->assertSame('maintenance', $room->status);
        $this->assertFalse($room->is_available);
        $this->assertSame(['rooms/live-edited-photo.jpg'], json_decode($room->getRawOriginal('images'), true));
    }
}
