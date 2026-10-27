<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\AccommodationLedger;
use Illuminate\Support\Facades\Log;

class BookingObserver
{
    /**
     * Notify operations that a pending reservation entered the shared queue.
     * Creating a booking never confirms that its payment has arrived.
     */
    public function created(Booking $booking): void
    {
        if ($booking->status !== 'pending') {
            return;
        }

        \Illuminate\Support\Facades\DB::afterCommit(function () use ($booking): void {
            $roomName = $booking->room?->name ?? 'Suite';
            $recipients = \App\Models\User::whereIn('role', [
                \App\Models\User::ROLE_ADMIN,
                \App\Models\User::ROLE_IPL_ADMIN,
                \App\Models\User::ROLE_DELIVERY_PARTNER,
                \App\Models\User::ROLE_GUEST_RELATIONS,
            ])->get();

            foreach ($recipients as $recipient) {
                $recipient->notify(new \App\Notifications\PaymentActivityNotification(
                    'New pending room booking',
                    "Booking #{$booking->id} for {$roomName} requires payment confirmation.",
                    $booking->id,
                    'pending'
                ));
            }
        });
    }

    /**
     * Handle the Booking "saved" event.
     */
    public function saved(Booking $booking): void
    {
        // Check if booking is confirmed or paid
        // A booking status alone is not proof that the money was received.
        $isPaid = !empty($booking->paid_at) && in_array(strtolower((string) $booking->status), ['confirmed', 'paid', 'completed'], true);

        if ($isPaid) {
            $existingLedger = AccommodationLedger::where('booking_id', $booking->id)
                ->where('type', 'revenue')
                ->first();

            if (!$existingLedger) {
                AccommodationLedger::create([
                        'booking_id' => $booking->id,
                        'room_id' => $booking->room_id,
                        'reference' => 'ACCOM-' . ($booking->payment_reference ?: 'BK' . $booking->id . '-' . time()),
                        'type' => 'revenue',
                        'gross_amount' => $booking->total_price,
                        'net_amount' => $booking->total_price,
                        'payment_method' => $booking->payment_method ?: 'flutterwave',
                        'channel' => 'app',
                        'status' => 'confirmed',
                        'collected_by' => 'ipl',
                        'transaction_date' => $booking->paid_at ?: now(),
                        'notes' => 'Automated split-ledger entry from Mobile App Booking #' . $booking->id,
                        'created_by' => $booking->user_id,
                    ]);
            }
        }

        // A booking being marked refunded is not provider/bank evidence that
        // funds were returned. A separate verified refund settlement workflow
        // must record the actual refund ledger transaction.

    }
}
