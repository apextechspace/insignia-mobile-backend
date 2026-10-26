<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\AccommodationLedger;
use Illuminate\Support\Facades\Log;

class BookingObserver
{
    /**
     * Handle the Booking "saved" event.
     */
    public function saved(Booking $booking): void
    {
        // Check if booking is confirmed or paid
        $isPaid = in_array(strtolower($booking->status), ['confirmed', 'paid']) || !empty($booking->paid_at);

        if ($isPaid) {
            $existingLedger = AccommodationLedger::where('booking_id', $booking->id)
                ->where('type', 'revenue')
                ->first();

            if (!$existingLedger) {
                try {
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
                } catch (\Throwable $e) {
                    Log::error('Failed to create split-ledger entry for booking #' . $booking->id, [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $isCancelledOrRefunded = in_array(strtolower((string) $booking->status), ['cancelled', 'refunded']);

        if ($isCancelledOrRefunded) {
            $hasRevenueEntry = AccommodationLedger::where('booking_id', $booking->id)
                ->where('type', 'revenue')
                ->exists();

            $existingRefund = AccommodationLedger::where('booking_id', $booking->id)
                ->where('type', 'refund')
                ->exists();

            if ($hasRevenueEntry && !$existingRefund) {
                try {
                    AccommodationLedger::create([
                        'booking_id' => $booking->id,
                        'room_id' => $booking->room_id,
                        'reference' => 'REF-' . ($booking->payment_reference ?: 'BK' . $booking->id . '-' . time()),
                        'type' => 'refund',
                        'gross_amount' => $booking->total_price,
                        'net_amount' => $booking->total_price,
                        'payment_method' => $booking->payment_method ?: 'flutterwave',
                        'channel' => 'app',
                        'status' => 'refunded',
                        'collected_by' => 'ipl',
                        'transaction_date' => now(),
                        'notes' => 'Automated reversal/refund entry for cancelled/refunded Booking #' . $booking->id,
                        'created_by' => $booking->user_id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to create split-ledger refund reversal for booking #' . $booking->id, [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
