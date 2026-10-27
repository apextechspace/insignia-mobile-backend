<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestRoomAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_room_is_absent_from_guest_catalogue_and_cannot_be_booked(): void
    {
        $guest = User::factory()->create(['role' => User::ROLE_USER]);
        $room = Room::create([
            'name' => 'Maintenance Suite',
            'type' => 'Suite',
            'price_per_night' => 45000,
            'is_available' => true,
            'status' => 'maintenance',
        ]);

        $this->actingAs($guest)->getJson('/api/rooms')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Maintenance Suite']);

        $this->actingAs($guest)->postJson('/api/bookings', [
            'room_id' => $room->id,
            'check_in' => today()->addDays(3)->toDateString(),
            'check_out' => today()->addDays(5)->toDateString(),
        ])->assertUnprocessable();

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_available_room_is_visible_in_catalogue(): void
    {
        $guest = User::factory()->create(['role' => User::ROLE_USER]);
        Room::create([
            'name' => 'Ready Suite',
            'type' => 'Suite',
            'price_per_night' => 45000,
            'is_available' => true,
            'status' => 'available',
        ]);

        $this->actingAs($guest)->getJson('/api/rooms')
            ->assertOk()->assertJsonFragment(['name' => 'Ready Suite']);
    }
}
