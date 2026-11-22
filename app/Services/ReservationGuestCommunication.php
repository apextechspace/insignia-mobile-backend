<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Notification;

class ReservationGuestCommunication
{
    public static function email(Booking $stay): ?string
    {
        foreach ([$stay->booking_contact['email'] ?? null, $stay->user?->email] as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) return $email;
        }
        return null;
    }

    public static function sendUpdate(Booking $stay, string $event): void
    {
        // This lifecycle applies to assisted reservations. Verified-payment
        // receipts remain the responsibility of ReservationConfirmationEmail.
        if ($stay->reserved_by === null && $stay->minimum_payment_kobo === null) return;
        $email = self::email($stay);
        if ($email === null) return;
        [$title, $text] = match ($event) {
            'external_recorded' => ['Your external reservation has been recorded', 'Guest Relations has recorded your confirmed external reservation at Insignia Court. The original channel payment and cancellation arrangements apply. This acknowledgement is not a payment receipt. Contact Guest Relations if anything differs from your channel confirmation.'],
            'channel_guest_verified' => ['Your channel payment has been verified', 'Our authorised team has verified your full accommodation payment through the booking channel. No duplicate accommodation payment is required. This confirms the guest payment arrangement; it is not a receipt for a bank payout from the channel to Insignia.'],
            'external_cancelled' => ['Your external cancellation has been recorded', 'Management has recorded the channel cancellation locally. This message does not confirm a refund payout. Any refund or outstanding charges must be reviewed under the original channel agreement.'],
            'held' => ['Your room is temporarily held', 'Guest Relations has created your reservation request. This is a temporary room hold, not a confirmed reservation or a payment receipt. Please arrange the required payment before the hold expires.'],
            'proof_submitted' => ['Payment proof received — verification pending', 'We have received your submitted payment proof. Our team will match it against the POS merchant record or actual bank credit. This acknowledgement is not a verified payment receipt.'],
            'proof_rejected' => ['Payment proof needs correction', 'Our team could not accept the submitted payment proof. This proof has not been credited to your reservation. Please contact Guest Relations to review it and provide the correct payment evidence. Your previously verified payments are unchanged.'],
            'checked_in' => ['Welcome — your check-in is complete', 'Your check-in has been completed. Welcome to Insignia Court. Please contact Guest Relations if you need assistance during your stay.'],
            'checked_out' => ['Your check-out is complete', 'Your check-out has been recorded. Thank you for staying at Insignia Court. We look forward to welcoming you again.'],
            default => throw new \InvalidArgumentException('Unsupported guest reservation update.'),
        };
        $verified = ExternalReservations::guestPaymentCleared($stay) ? AssistedReservationPayments::kobo($stay->total_price) : AssistedReservationPayments::verified($stay);
        $details = ReservationConfirmationEmail::details($stay, 0, $verified, 'Booking-'.$stay->id, '', '');
        $details = array_merge($details, ['reservation_update' => true, 'receipt' => false,
            'title' => $title, 'subject' => $title.' | Insignia Court Booking-'.$stay->id,
            'update_text' => $text, 'stay_stage' => \App\Filament\Operations\Resources\GuestStayResource::stayStage($stay),
            'balance_text' => ExternalReservations::balanceText($stay),
            'hold_expires_at' => $stay->hold_expires_at ? $stay->hold_expires_at->timezone(config('property.timezone'))->format('j M Y, g:i A').' (Nigeria time)' : null,
        ]);
        Notification::route('mail', $email)->notify(new \App\Notifications\CustomerPaymentEmail($details));
    }

    public static function whatsappUrl(Booking $stay): ?string
    {
        $raw = trim((string) ($stay->booking_contact['phone'] ?? $stay->guest_registration['phone'] ?? $stay->user?->phone));
        $phone = preg_replace('/\D/', '', $raw);
        if (str_starts_with($phone, '00')) $phone = substr($phone, 2);
        elseif (strlen($phone) === 11 && str_starts_with($phone, '0')) $phone = '234'.substr($phone, 1);
        elseif (strlen($phone) === 10 && ! str_starts_with($raw, '+')) $phone = '234'.$phone;
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $phone)) return null;
        $verified = ExternalReservations::guestPaymentCleared($stay) ? AssistedReservationPayments::kobo($stay->total_price) : AssistedReservationPayments::verified($stay);
        $pending = $stay->paid_at ? 0 : AssistedReservationPayments::pending($stay)->sum('amount_kobo');
        $money = fn ($kobo) => 'NGN '.number_format($kobo / 100, 2);
        $name = $stay->booking_contact['name'] ?? $stay->user?->name ?? 'Guest';
        $text = "Dear {$name},\nInsignia Court — Booking #{$stay->id}\nRoom / suite: {$stay->room?->name}\nArrival: {$stay->check_in}\nDeparture: {$stay->check_out}\nStatus: ".\App\Filament\Operations\Resources\GuestStayResource::stayStage($stay)
            ."\nStay total: ".$money(AssistedReservationPayments::kobo($stay->total_price))."\nVerified payments: ".$money($verified)
            ."\nBalance before check-in: ".(ExternalReservations::balanceText($stay) ?? $money(max(0, AssistedReservationPayments::kobo($stay->total_price) - $verified)));
        if ($stay->external_source) $text .= "\nBooked through: ".ExternalReservations::sourceLabel($stay)."\nChannel reference: {$stay->external_reference} / {$stay->external_unit_reference}";
        if ($pending > 0) $text .= "\nSubmitted proof awaiting verification: ".$money($pending).'. This amount is not yet verified.';
        if ($stay->status === 'pending' && $stay->hold_expires_at) $text .= "\nTemporary hold expires: ".$stay->hold_expires_at->timezone(config('property.timezone'))->format('j M Y, g:i A').' (Nigeria time).';
        if (!ExternalReservations::guestPaymentCleared($stay) && ExternalReservations::balanceText($stay) === null) $text .= "\nFull verified payment is required before check-in.";
        $text .= "\nWarm regards, Guest Relations — Insignia Court";
        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }
}
