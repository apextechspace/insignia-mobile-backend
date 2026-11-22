<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReservationTerms
{
    public static function snapshot(string $arrival, string $departure, string $total, bool $assisted, ?int $minimum = null): array
    {
        $zone = config('property.timezone');
        $arrival = CarbonImmutable::parse($arrival)->toDateString();
        $departure = CarbonImmutable::parse($departure)->toDateString();
        $checkIn = CarbonImmutable::parse($arrival.' '.config('property.check_in_time'), $zone);
        $checkOut = CarbonImmutable::parse($departure.' '.config('property.check_out_time'), $zone);
        $nights = max(1, (int) CarbonImmutable::parse($arrival)->diffInDays(CarbonImmutable::parse($departure)));
        $policy = DB::table('reservation_payment_policy')->find(1);
        $long = $nights >= 7;
        $chargeNights = $long ? 3 : 1;
        $charge = min(AssistedReservationPayments::kobo($total), (int) ceil(AssistedReservationPayments::kobo($total) * $chargeNights / $nights));
        $deadline = $long ? $checkIn->subDays(7) : $checkIn->subHours(48);
        if ($long && $policy->late_booking_grace_enabled && now($zone)->gte($checkIn->subDays(7))) {
            $grace = CarbonImmutable::now($zone)->addHours(24);
            $deadline = $grace->lt($checkIn) ? $grace : $checkIn;
        }

        return [
            'version' => 2, 'nights' => $nights,
            'refund_admin_fee_percent' => 10, 'refund_processing_hours_after_checkout' => 24,
            'deposit_mode' => $assisted && $policy->deposits_enabled ? $policy->deposit_mode : 'full',
            'minimum_kobo' => $assisted ? ($minimum ?? AssistedReservationPayments::minimum($total, $nights)) : AssistedReservationPayments::kobo($total),
            'cancellation_enabled' => (bool) $policy->cancellation_terms_enabled,
            'cancellation_charge_kobo' => $charge, 'cancellation_charge_nights' => $chargeNights,
            'refund_deadline' => $deadline->toIso8601String(),
            'booked_after_deadline' => now($zone)->gt($deadline),
            'check_in_at' => $checkIn->toIso8601String(), 'check_out_at' => $checkOut->toIso8601String(),
            'release_review_at' => $long ? $checkIn->addDay()->setTime(12, 0)->toIso8601String() : null,
        ];
    }

    public static function summary(array $terms): string
    {
        if (!empty($terms['external'])) return 'External reservation. Payment and cancellation arrangements follow the original channel confirmation: '.($terms['channel_terms'] ?? 'Refer to the channel confirmation.');
        $text = 'Required verified payment: ₦'.number_format($terms['minimum_kobo'] / 100, 2).'. Remaining balance must be verified before check-in. Final room prices already include any owner-applied taxes.';
        if ($terms['cancellation_enabled']) {
            $text .= (isset($terms['refund_admin_fee_percent']) ? ' Cancellation notice deadline: ' : ' Full-refund deadline: ').CarbonImmutable::parse($terms['refund_deadline'])->format('D, j M Y, g:i A').' Nigeria time.'.(! empty($terms['booked_after_deadline']) ? ' This booking is already outside that free-cancellation window.' : '').' Later cancellations or no-shows: retain up to ₦'.number_format($terms['cancellation_charge_kobo'] / 100, 2).' of money received. Contact Guest Relations; refunds and room release require manual handling.';
        }
        if (isset($terms['refund_admin_fee_percent'])) {
            $text .= ' Eligible refunds are subject to a '.$terms['refund_admin_fee_percent'].'% administration fee on the refundable amount after used nights and approved charges; no additional VAT deduction. Eligible post-checkout refunds are processed within '.$terms['refund_processing_hours_after_checkout'].' hours after checkout. Refund eligibility and payout require management review.';
        }

        return $text;
    }
}
