<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualBookingPaymentReview
{
    public function approve(Booking $booking, User $reviewer, string $bankReference, string $amount, ?string $notes = null): bool
    {
        if (!$reviewer->isIplAdmin()) {
            throw ValidationException::withMessages(['reviewer' => 'Only IPL finance administrators can approve bank transfers.']);
        }

        $bankReference = trim($bankReference);
        if (!$bankReference || !preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw ValidationException::withMessages(['verified_amount' => 'Enter a valid verified bank amount and reference.']);
        }

        $confirmed = DB::transaction(function () use ($booking, $reviewer, $bankReference, $amount, $notes) {
            // Lock the suite before its booking; same ordering as Flutterwave settlement.
            \App\Models\Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            $record = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($record->paid_at || $record->status !== 'pending' || !$record->getRawOriginal('receipt_url')) {
                throw ValidationException::withMessages(['booking' => 'Booking is not awaiting manual receipt verification.']);
            }
            if (!in_array($record->payment_method, [null, 'bank_transfer'], true)) {
                throw ValidationException::withMessages(['booking' => 'Only manual bank transfers may be approved here.']);
            }
            if ((int) round((float) $amount * 100) !== (int) round((float) $record->total_price * 100)) {
                throw ValidationException::withMessages(['verified_amount' => 'Bank credit does not match the booking total.']);
            }
            if (DB::table('manual_payment_reviews')->where('bank_transaction_reference', $bankReference)->exists()) {
                throw ValidationException::withMessages(['bank_transaction_reference' => 'This bank transaction has already been reviewed.']);
            }

            $conflict = Booking::where('room_id', $record->room_id)
                ->whereKeyNot($record->id)
                ->whereIn('status', ['confirmed', 'paid', 'completed'])
                ->where('check_in', '<', $record->check_out)
                ->where('check_out', '>', $record->check_in)
                ->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['booking' => 'These room dates are occupied. Escalate the bank transfer for reconciliation.']);
            }

            // Reviewer attests bank credit was independently checked. A screenshot isn't proof.
            DB::table('manual_payment_reviews')->insert([
                'booking_id' => $record->id,
                'reviewer_id' => $reviewer->id,
                'bank_transaction_reference' => $bankReference,
                'verified_amount' => $amount,
                'decision' => 'approved',
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $record->update(['paid_at' => now(), 'payment_method' => 'bank_transfer', 'status' => 'confirmed']);
            // Database notifications are committed atomically with payment and ledger.
            $this->notifyBooking($record->id, true);
            app(CustomerPaymentEmailService::class)->send($record, true, $bankReference);
            return $record->id;
        }, 3);

        return (bool) $confirmed;
    }

    public function reject(Booking $booking, User $reviewer, string $notes): void
    {
        if (!$reviewer->isIplAdmin() || trim($notes) === '') {
            throw ValidationException::withMessages(['reviewer' => 'IPL finance approval and a rejection reason are required.']);
        }

        $bookingId = DB::transaction(function () use ($booking, $reviewer, $notes) {
            $record = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($record->status !== 'pending' || $record->paid_at || $record->payment_method !== 'bank_transfer') {
                throw ValidationException::withMessages(['booking' => 'Only pending bank-transfer receipts can be rejected.']);
            }
            DB::table('manual_payment_reviews')->insert([
                'booking_id' => $record->id,
                'reviewer_id' => $reviewer->id,
                'bank_transaction_reference' => 'REJECTED-' . \Illuminate\Support\Str::uuid(),
                'verified_amount' => 0,
                'decision' => 'rejected',
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Leave the original payable pending for a corrected receipt or alternative payment.
            $this->notifyBooking($record->id, false);
            return $record->id;
        });
    }

    private function notifyBooking(int $bookingId, bool $approved): void
    {
        $booking = Booking::with(['room', 'user'])->find($bookingId);
        if (!$booking) {
            return;
        }
        $name = $booking->room?->name ?? 'suite';
        $title = $approved ? 'Suite booking confirmed' : 'Bank transfer receipt needs attention';
        $message = $approved
            ? "Your reservation for {$name} is confirmed after bank verification."
            : "Your bank receipt for {$name} could not be verified. Please contact support.";

        $users = User::whereIn('role', [
            User::ROLE_ADMIN, User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER, User::ROLE_GUEST_RELATIONS,
        ])->get();
        if ($booking->user) {
            $users->push($booking->user);
        }
        foreach ($users->unique('id') as $user) {
            $user->notify(new \App\Notifications\PaymentActivityNotification(
                $title, $message, $booking->id, $approved ? 'confirmed' : 'needs_review'
            ));
        }
    }
}
