<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssistedReservationPayments
{
    public static function minimum(string $total): int
    {
        $policy = DB::table('reservation_payment_policy')->find(1);
        $kobo = self::kobo($total);
        $rate = $policy->deposits_enabled ? self::kobo($policy->minimum_percent) : 10000;

        return intdiv($kobo * $rate + 9999, 10000);
    }

    public static function kobo(string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public static function entries(Booking $booking): \Illuminate\Database\Query\Builder
    {
        return DB::table('assisted_reservation_payments')->where('booking_id', $booking->id);
    }

    public static function verified(Booking $booking): int
    {
        return (int) self::entries($booking)->whereNotNull('verified_at')->sum('amount_kobo');
    }

    public static function balance(Booking $booking): int
    {
        return max(0, self::kobo($booking->total_price) - self::verified($booking));
    }

    public static function reviewHoldExpiry(Booking $booking): ?\Illuminate\Support\Carbon
    {
        if ($booking->status !== 'pending' || ! $booking->hold_expires_at?->isFuture()
            || self::entries($booking)->exists()) {
            return null;
        }

        $deadline = now()->addMinutes(config('property.pos_review_hold_minutes'));

        return $deadline->gt($booking->hold_expires_at) ? $deadline : null;
    }

    public function verify(Booking $booking, int $paymentId, User $reviewer): void
    {
        abort_unless(self::canVerify($reviewer), 403);

        try {
            DB::transaction(function () use ($booking, $paymentId, $reviewer): void {
                $room = Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
                $stay = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
                $payment = self::entries($stay)->where('id', $paymentId)->first();
                if (! $payment || $payment->verified_at || ! self::canCollect($stay)) {
                    throw ValidationException::withMessages(['payment_id' => 'This payment is no longer awaiting verification.']);
                }

                $total = self::kobo($stay->total_price);
                $verified = self::verified($stay) + $payment->amount_kobo;
                if ($verified > $total) {
                    throw ValidationException::withMessages(['payment_id' => 'This payment exceeds the remaining balance. Reconcile it before posting.']);
                }
                $secured = $verified >= ($stay->minimum_payment_kobo ?? $total);
                if ($secured && $stay->status === 'pending') {
                    self::assertCanSecure($stay, $room);
                }

                DB::table('manual_payment_reviews')->insert([
                    'booking_id' => $stay->id,
                    'reviewer_id' => $reviewer->id,
                    'bank_transaction_reference' => $payment->reference,
                    'verified_amount' => $payment->amount_kobo / 100,
                    'decision' => 'approved',
                    'notes' => 'Assisted POS payment #'.$payment->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                self::entries($stay)->where('id', $paymentId)->update([
                    'verified_by' => $reviewer->id, 'verified_at' => now(), 'updated_at' => now(),
                ]);

                if ($secured && $stay->status === 'pending') {
                    $stay->status = 'confirmed';
                    $stay->hold_expires_at = null;
                }
                if ($verified === $total) {
                    $stay->paid_at = now();
                    $stay->payment_method = 'pos';
                }
                $stay->save();
                $email = $stay->booking_contact['email'] ?? null;
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    \Illuminate\Support\Facades\Notification::route('mail', $email)->notify(new \App\Notifications\CustomerPaymentEmail([
                        'title' => 'POS payment receipt',
                        'receipt' => true,
                        'number' => 'Booking-'.$stay->id,
                        'customer_name' => $stay->booking_contact['name'] ?? 'Guest',
                        'description' => $room->name,
                        'amount' => number_format($payment->amount_kobo / 100, 2),
                        'balance_due' => number_format(($total - $verified) / 100, 2),
                        'reservation_status' => $stay->status === 'confirmed' ? 'Dates secured' : 'Dates not yet secured',
                        'reference' => $payment->reference,
                        'method' => 'POS',
                        'date' => $payment->transaction_date,
                        'check_in' => $stay->check_in,
                        'check_out' => $stay->check_out,
                    ]));
                }
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['payment_id' => 'This bank transaction reference has already been credited.']);
        }
    }

    public static function canVerify(?User $reviewer): bool
    {
        return $reviewer !== null && ($reviewer->isIplAdmin()
            || ($reviewer->isDeliveryPartner()
                && (bool) DB::table('reservation_payment_policy')->where('id', 1)->value('vrl_can_verify_pos')));
    }

    public function approveDepositException(Booking $booking, User $reviewer, string $reason): void
    {
        abort_unless($reviewer->isIplAdmin(), 403);
        DB::transaction(function () use ($booking, $reviewer, $reason): void {
            $room = Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            $stay = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $verified = self::verified($stay);
            if (! self::canCollect($stay) || $stay->status !== 'pending' || $verified <= 0 || trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'An exception requires a pending reservation, a verified deposit and a reason.']);
            }
            self::assertCanSecure($stay, $room);
            $stay->forceFill([
                'status' => 'confirmed',
                'hold_expires_at' => null,
                'deposit_exception_by' => $reviewer->id,
                'deposit_exception_reason' => trim($reason),
                'deposit_exception_at' => now(),
            ])->save();
        }, 3);
    }

    private static function assertCanSecure(Booking $stay, Room $room): void
    {
        if ($stay->check_out <= today(config('property.timezone'))->toDateString()
            || ! $room->is_available || $room->status === 'maintenance'
            || RoomAvailability::blocking($stay->check_in, $stay->check_out, $stay->id)->where('room_id', $room->id)->exists()) {
            throw ValidationException::withMessages(['booking' => 'The dates are unavailable. Review the payment with finance before proceeding.']);
        }
    }

    public static function canCollect(Booking $stay): bool
    {
        return ($stay->reserved_by !== null || $stay->minimum_payment_kobo !== null)
            && ! $stay->paid_at && ! $stay->checked_in_at && ! $stay->checked_out_at
            && in_array($stay->status, ['pending', 'confirmed'], true);
    }
}
