<?php

namespace App\Services;

use App\Models\AccommodationLedger;
use App\Models\Booking;
use Illuminate\Support\Collection;

/**
 * Read-only financial integrity checks. Exceptions require IPL review;
 * this service never infers that a bank or gateway refund was issued.
 */
class AccommodationReconciliation
{
    public function exceptions(): Collection
    {
        $exceptions = collect();

        Booking::query()->whereNotNull('paid_at')->with('accommodationLedger')
            ->chunkById(200, function ($bookings) use ($exceptions): void {
                foreach ($bookings as $booking) {
                    $revenues = AccommodationLedger::where('booking_id', $booking->id)
                        ->where('type', 'revenue')->get();
                    if ($booking->status === 'refunded'
                        && !AccommodationLedger::where('booking_id', $booking->id)
                            ->where('type', 'refund')->exists()) {
                        $exceptions->push($this->row($booking, 'refund_status_without_evidence',
                            'Booking says refunded but no reviewed refund ledger entry exists. Confirm bank/provider payout evidence.'));
                    }
                    if ($revenues->count() === 0) {
                        $exceptions->push($this->row($booking, 'paid_booking_missing_revenue',
                            'Paid booking has no accommodation revenue entry.'));
                    } elseif ($revenues->count() > 1) {
                        $exceptions->push($this->row($booking, 'duplicate_revenue',
                            'Multiple accommodation revenue entries reference this booking.'));
                    } elseif ((int) round((float) $revenues->first()->gross_amount * 100)
                        !== (int) round((float) $booking->total_price * 100)) {
                        $exceptions->push($this->row($booking, 'revenue_amount_mismatch',
                            'Revenue entry amount differs from recorded booking price.'));
                    }
                }
            });

        AccommodationLedger::query()->whereIn('type', ['revenue', 'refund'])
            ->whereNotNull('booking_id')->with('booking')
            ->chunkById(200, function ($entries) use ($exceptions): void {
                foreach ($entries as $entry) {
                    if (!$entry->booking) {
                        $exceptions->push([
                            'booking_id' => $entry->booking_id,
                            'reference' => $entry->reference,
                            'issue' => 'ledger_booking_missing',
                            'description' => 'Ledger entry references a missing booking.',
                        ]);
                        continue;
                    }

                    if ($entry->type === 'revenue' && !$entry->booking->paid_at) {
                        $exceptions->push($this->row($entry->booking, 'unpaid_booking_has_revenue',
                            'Revenue ledger entry exists without a booking paid timestamp.'));
                    }

                    if ($entry->type === 'refund' && $entry->booking->status !== 'refunded') {
                        $exceptions->push($this->row($entry->booking, 'refund_state_mismatch',
                            'Refund ledger exists without a refunded booking state. Confirm provider evidence.'));
                    }
                }
            });

        return $exceptions;
    }

    private function row(Booking $booking, string $issue, string $description): array
    {
        return [
            'booking_id' => $booking->id,
            'reference' => $booking->flutterwave_reference
                ?: ($booking->payment_reference ?: 'BK-' . $booking->id),
            'issue' => $issue,
            'description' => $description,
        ];
    }
}
