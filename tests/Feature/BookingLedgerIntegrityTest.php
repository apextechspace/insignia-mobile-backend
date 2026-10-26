<?php

namespace Tests\Feature;

use App\Models\AccommodationLedger;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingLedgerIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function booking(): Booking
    {
        $user = User::factory()->create();

        $room = Room::create([
            'name' => 'Atala',
            'type' => 'Suite',
            'price_per_night' => 50000,
            'status' => 'available',
        ]);

        return Booking::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'check_in' => now()->addDays(3)->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
            'total_price' => 100000,
            'status' => 'pending',
        ]);
    }

    public function test_confirmed_status_without_payment_is_not_recognized_as_revenue(): void
    {
        $booking = $this->booking();
        $booking->update(['status' => 'confirmed']);

        $this->assertSame(0, AccommodationLedger::where('booking_id', $booking->id)
            ->where('type', 'revenue')->count());
    }

    public function test_verified_payment_creates_one_revenue_entry(): void
    {
        $booking = $this->booking();
        $booking->update([
            'status' => 'confirmed',
            'payment_method' => 'flutterwave',
            'paid_at' => now(),
        ]);

        $booking->update(['status' => 'confirmed']);
        $this->assertSame(1, AccommodationLedger::where('booking_id', $booking->id)
            ->where('type', 'revenue')->count());
    }

    public function test_cancelling_paid_booking_does_not_falsely_record_refund(): void
    {
        $booking = $this->booking();
        $booking->update([
            'status' => 'confirmed',
            'payment_method' => 'flutterwave',
            'paid_at' => now(),
        ]);

        $booking->update(['status' => 'cancelled']);

        $this->assertSame(0, AccommodationLedger::where('booking_id', $booking->id)
            ->where('type', 'refund')->count());
    }
}
