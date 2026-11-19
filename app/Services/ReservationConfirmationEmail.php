<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\CarbonImmutable;

class ReservationConfirmationEmail
{
    public static function details(Booking $stay, int $received, int $verified, string $reference, string $method, string $date): array
    {
        $terms = $stay->reservation_terms;
        $nights = $terms['nights'] ?? max(1, (int) CarbonImmutable::parse($stay->check_in)->diffInDays(CarbonImmutable::parse($stay->check_out)));
        $total = AssistedReservationPayments::kobo($stay->total_price);
        $full = $verified >= $total;
        $confirmed = $full || in_array($stay->status, ['confirmed', 'paid', 'completed'], true);
        $title = $full ? 'Payment received in full' : ($confirmed ? ($nights >= 7 ? 'Your extended stay is confirmed' : 'Your reservation is confirmed') : 'Payment received — reservation awaiting confirmation');

        return [
            'reservation_email' => true, 'receipt' => true, 'title' => $title,
            'subject' => $title.' | Insignia Court reservation Booking-'.$stay->id,
            'number' => 'Booking-'.$stay->id,
            'customer_name' => $stay->booking_contact['name'] ?? $stay->user?->name ?? 'Guest',
            'room_name' => $stay->room?->name, 'room_category' => $stay->room?->type,
            'nights' => $nights, 'full' => $full, 'confirmed' => $confirmed,
            'night_based' => ($terms['deposit_mode'] ?? null) === 'night_based' && $verified >= ($terms['minimum_kobo'] ?? $total),
            'terms' => $terms,
            'amount_received_kobo' => $received, 'total_paid_kobo' => $verified,
            'balance_kobo' => max(0, $total - $verified), 'total_kobo' => $total,
            'reference' => $reference, 'method' => $method, 'date' => $date,
            'check_in_at' => $terms['check_in_at'] ?? $stay->check_in.'T'.config('property.check_in_time').':00+01:00',
            'check_out_at' => $terms['check_out_at'] ?? $stay->check_out.'T'.config('property.check_out_time').':00+01:00',
            'phone' => ltrim((string) config('property.whatsapp_number'), '+'),
        ];
    }

    public static function sendAssisted(Booking $stay, object $payment, bool $confirmationOnly = false): void
    {
        $email = ReservationGuestCommunication::email($stay);
        if ($email === null) {
            return;
        }
        $details = self::details($stay, $payment->amount_kobo, AssistedReservationPayments::verified($stay),
            $payment->reference, AssistedReservationPayments::METHODS[$payment->method], $payment->transaction_date);
        $details['confirmation_only'] = $confirmationOnly;
        \Illuminate\Support\Facades\Notification::route('mail', $email)->notify(new \App\Notifications\CustomerPaymentEmail($details));
    }
}
