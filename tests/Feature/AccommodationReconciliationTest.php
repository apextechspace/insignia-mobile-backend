<?php

namespace Tests\Feature;

use App\Models\AccommodationLedger;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\AccommodationReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccommodationReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function booking(): Booking
    {
        $room = Room::create([
            'name' => 'Ledger Suite', 'type' => 'Suite', 'price_per_night' => 55000,
            'status' => 'available',
        ]);
        return Booking::create([
            'user_id' => User::factory()->create()->id,
            'room_id' => $room->id,
            'check_in' => today()->addDays(4)->toDateString(),
            'check_out' => today()->addDays(6)->toDateString(),
            'total_price' => 110000,
            'status' => 'pending',
        ]);
    }

    private function issueNames(): array
    {
        return app(AccommodationReconciliation::class)->exceptions()
            ->pluck('issue')->all();
    }

    public function test_paid_booking_with_normal_ledger_has_no_exception(): void
    {
        $booking = $this->booking();
        $booking->update(['paid_at' => now(), 'status' => 'confirmed', 'payment_method' => 'flutterwave']);
        $this->assertSame([], $this->issueNames());
    }

    public function test_paid_booking_without_revenue_is_reported_not_silently_repaired(): void
    {
        $booking = $this->booking();
        $booking->update(['paid_at' => now(), 'status' => 'confirmed', 'payment_method' => 'flutterwave']);
        AccommodationLedger::where('booking_id', $booking->id)->delete();

        $this->assertContains('paid_booking_missing_revenue', $this->issueNames());
        $this->assertDatabaseCount('accommodation_ledgers', 0);
    }

    public function test_mismatched_revenue_amount_is_reported(): void
    {
        $booking = $this->booking();
        $booking->update(['paid_at' => now(), 'status' => 'confirmed', 'payment_method' => 'flutterwave']);
        AccommodationLedger::where('booking_id', $booking->id)->where('type', 'revenue')
            ->update(['gross_amount' => 90000]);
        $this->assertContains('revenue_amount_mismatch', $this->issueNames());
    }

    public function test_refunded_booking_without_refund_evidence_is_flagged(): void
    {
        $booking = $this->booking();
        $booking->update(['paid_at' => now(), 'status' => 'confirmed', 'payment_method' => 'flutterwave']);
        $booking->update(['status' => 'refunded']);

        $this->assertContains('refund_status_without_evidence', $this->issueNames());
        $this->assertDatabaseMissing('accommodation_ledgers', [
            'booking_id' => $booking->id, 'type' => 'refund',
        ]);
    }

    public function test_cancellation_does_not_forge_refund_evidence(): void
    {
        $booking = $this->booking();
        $booking->update(['paid_at' => now(), 'status' => 'confirmed', 'payment_method' => 'flutterwave']);
        $booking->update(['status' => 'cancelled']);
        $this->assertNotContains('refund_state_mismatch', $this->issueNames());
        $this->assertDatabaseMissing('accommodation_ledgers', [
            'booking_id' => $booking->id, 'type' => 'refund',
        ]);
    }
}
