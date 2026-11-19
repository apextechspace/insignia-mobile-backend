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
    public const METHODS = ['pos' => 'POS', 'bank_transfer' => 'Direct bank transfer'];

    public static function minimum(string $total, int $nights = 1): int
    {
        $policy = DB::table('reservation_payment_policy')->find(1);
        $kobo = self::kobo($total);
        if ($policy->deposits_enabled && $policy->deposit_mode === 'night_based') {
            return min($kobo, (int) ceil($kobo * ($nights >= 7 ? 3 : 1) / max(1, $nights)));
        }
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

    public static function pending(Booking $booking): \Illuminate\Database\Query\Builder
    {
        return self::entries($booking)->whereNull('verified_at')->whereNull('rejected_at');
    }

    public function reject(Booking $booking, int $paymentId, User $reviewer, string $reason): void
    {
        abort_unless(self::canVerify($reviewer), 403);
        if (trim($reason) === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Enter a reason of up to 1,000 characters.']);
        }
        DB::transaction(function () use ($booking, $paymentId, $reviewer, $reason) {
            Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            $stay = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $payment = self::pending($stay)->where('id', $paymentId)->first();
            if (! $payment) throw ValidationException::withMessages(['payment_id' => 'Only an unverified, unreviewed proof can be rejected.']);
            self::entries($stay)->where('id', $paymentId)->update(['rejected_at' => now(), 'rejected_by' => $reviewer->id, 'rejection_reason' => trim($reason), 'updated_at' => now()]);
            ReservationGuestCommunication::sendUpdate($stay, 'proof_rejected');
        }, 3);
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
                if (! $payment || $payment->verified_at || $payment->rejected_at || ! self::canCollect($stay)) {
                    throw ValidationException::withMessages(['payment_id' => 'This payment is no longer awaiting verification.']);
                }

                $total = self::kobo($stay->total_price);
                if ($stay->status === 'confirmed' && (int) $payment->amount_kobo !== self::balance($stay)) {
                    throw ValidationException::withMessages(['payment_id' => 'The balance payment must match the full outstanding balance. Reject incorrect proof and record the correct payment.']);
                }
                $verified = self::verified($stay) + $payment->amount_kobo;
                if ($verified > $total) {
                    throw ValidationException::withMessages(['payment_id' => 'This payment exceeds the remaining balance. Reconcile it before posting.']);
                }
                $secured = $verified >= ($stay->minimum_payment_kobo ?? $total);
                if ($secured && $stay->status === 'pending') {
                    self::assertCanSecure($stay, $room);
                }

                $bankReference = strtoupper(trim($payment->reference));
                if ($bankReference === '' || DB::table('manual_payment_reviews')->whereRaw('UPPER(TRIM(bank_transaction_reference)) = ?', [$bankReference])->exists()) {
                    throw ValidationException::withMessages(['payment_id' => 'This bank transaction reference has already been credited.']);
                }
                DB::table('manual_payment_reviews')->insert([
                    'booking_id' => $stay->id,
                    'reviewer_id' => $reviewer->id,
                    'bank_transaction_reference' => $bankReference,
                    'verified_amount' => $payment->amount_kobo / 100,
                    'decision' => 'approved',
                    'notes' => 'Assisted payment #'.$payment->id.' ('.self::METHODS[$payment->method].')',
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
                    $methods = self::entries($stay)->whereNotNull('verified_at')->distinct()->pluck('method');
                    $stay->payment_method = $methods->count() === 1 ? $methods->first() : 'mixed';
                }
                $stay->save();
                ReservationConfirmationEmail::sendAssisted($stay, $payment);
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
            $last = self::entries($stay)->whereNotNull('verified_at')->latest('id')->first();
            if ($last) {
                // A confirmation after an owner exception is not a second receipt.
                $notice = clone $last;
                $notice->amount_kobo = 0;
                ReservationConfirmationEmail::sendAssisted($stay, $notice, true);
            }
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
