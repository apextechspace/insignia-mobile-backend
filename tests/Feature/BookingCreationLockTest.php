<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCreationLockTest extends TestCase
{
    use RefreshDatabase;

    private function suite(): Room
    {
        return Room::create([
            'name' => 'Booking Integrity Suite',
            'type' => 'Suite',
            'price_per_night' => 55000,
            'status' => 'available',
            'is_available' => true,
        ]);
    }

    public function test_confirmed_reservation_blocks_guest_overlapping_dates(): void
    {
        $room = $this->suite();
        Booking::create([
            'room_id' => $room->id,
            'user_id' => User::factory()->create()->id,
            'check_in' => today()->addDays(5)->toDateString(),
            'check_out' => today()->addDays(8)->toDateString(),
            'total_price' => 190000,
            'status' => 'confirmed',
            'payment_method' => 'flutterwave',
            'paid_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/bookings', [
                'room_id' => $room->id,
                'check_in' => today()->addDays(7)->toDateString(),
                'check_out' => today()->addDays(9)->toDateString(),
            ])->assertUnprocessable();

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_active_checkout_hold_prevents_a_second_unpaid_reservation(): void
    {
        $room = $this->suite();
        $dates = [
            'room_id' => $room->id,
            'check_in' => today()->addDays(5)->toDateString(),
            'check_out' => today()->addDays(7)->toDateString(),
        ];

        $this->actingAs(User::factory()->create())->postJson('/api/bookings', $dates)
            ->assertCreated()->assertJsonPath('status', 'pending');
        $this->actingAs(User::factory()->create())->postJson('/api/bookings', $dates)
            ->assertUnprocessable();

        $this->assertSame(1, Booking::where('room_id', $room->id)
            ->where('status', 'pending')->count());
    }
}
