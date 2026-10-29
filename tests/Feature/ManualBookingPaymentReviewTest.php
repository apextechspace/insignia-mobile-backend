<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\ManualBookingPaymentReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManualBookingPaymentReviewTest extends TestCase
{
    use RefreshDatabase;

    private function booking(): Booking
    {
        $room = Room::create([
            'name' => 'Junonia', 'type' => 'Suite', 'price_per_night' => 50000,
            'status' => 'available',
        ]);
        return Booking::create([
            'user_id' => User::factory()->create()->id,
            'room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'total_price' => 100000,
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'receipt_url' => 'receipts/evidence.jpg',
        ]);
    }

    public function test_admin_verified_credit_confirms_booking_once_and_notifies_guest(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $booking = $this->booking();
        $reviewer = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);

        app(ManualBookingPaymentReview::class)
            ->approve($booking, $reviewer, 'TEST-BANK-001', '100000.00', 'Verified bank credit');

        $this->assertNotNull($booking->fresh()->paid_at);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseHas('manual_payment_reviews', [
            'booking_id' => $booking->id,
            'reviewer_id' => $reviewer->id,
            'bank_transaction_reference' => 'TEST-BANK-001',
            'decision' => 'approved',
        ]);
        $this->assertDatabaseCount('accommodation_ledgers', 1);
        $this->assertTrue($booking->user->notifications()->exists());
        \Illuminate\Support\Facades\Queue::assertPushed(\Illuminate\Notifications\SendQueuedNotifications::class, function ($job) use ($booking) {
            return $job->notification instanceof \App\Notifications\CustomerPaymentEmail
                && $job->notifiables->first()->id === $booking->user_id
                && $job->notification->details['receipt'] === true
                && $job->notification->details['reference'] === 'TEST-BANK-001';
        });
    }

    public function test_non_owner_cannot_approve_manual_transfer(): void
    {
        $booking = $this->booking();
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $this->expectException(ValidationException::class);
        app(ManualBookingPaymentReview::class)->approve($booking, $staff, 'TEST-BANK-002', '100000.00');
    }

    public function test_wrong_amount_is_rejected_without_payment_mutation(): void
    {
        $booking = $this->booking();
        $reviewer = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);

        try {
            app(ManualBookingPaymentReview::class)->approve($booking, $reviewer, 'TEST-BANK-003', '90000.00');
            $this->fail('Expected incorrect bank credit to be rejected');
        } catch (ValidationException $e) {
            $this->assertNull($booking->fresh()->paid_at);
            $this->assertDatabaseCount('manual_payment_reviews', 0);
        }
    }

    public function test_receipt_missing_cannot_be_verified(): void
    {
        $booking = $this->booking();
        $booking->update(['receipt_url' => null]);
        $reviewer = User::factory()->create(['role' => User::ROLE_IPL_ADMIN]);

        $this->expectException(ValidationException::class);
        app(ManualBookingPaymentReview::class)->approve($booking, $reviewer, 'TEST-BANK-004', '100000.00');
    }
}
