<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingDashboardSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_booking_notifies_ipl_and_mcl_without_confirming_payment(): void
    {
        $guest = User::factory()->create(['role' => User::ROLE_USER]);
        $owner = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);
        $operator = User::factory()->create(['role' => User::ROLE_DELIVERY_PARTNER]);
        $room = Room::create([
            'name' => 'Atala', 'type' => 'Suite',
            'price_per_night' => 40000, 'status' => 'available',
        ]);

        $booking = Booking::create([
            'user_id' => $guest->id,
            'room_id' => $room->id,
            'check_in' => now()->addDays(8)->toDateString(),
            'check_out' => now()->addDays(9)->toDateString(),
            'total_price' => 65000,
            'status' => 'pending',
        ]);

        $this->assertNull($booking->fresh()->paid_at);
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertSame(0, $guest->notifications()->count());
        $this->assertSame(1, $owner->notifications()->where('data->status', 'pending')->count());
        $this->assertSame(1, $operator->notifications()->where('data->status', 'pending')->count());
    }
}
