<?php

namespace App\Services;

use App\Models\{Booking, User};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class ExternalReservations
{
    public const SOURCES = ['booking_com' => 'Booking.com', 'airbnb' => 'Airbnb', 'expedia' => 'Expedia', 'other' => 'Other channel'];
    public const ARRANGEMENTS = ['pay_at_property' => 'Guest pays Insignia directly', 'channel_collects' => 'Channel collects from guest; settlement is separate', 'review_required' => 'Not confirmed — manager review required'];

    public static function sourceLabel(Booking $stay): string
    {
        return $stay->external_details['source_label'] ?? self::SOURCES[$stay->external_source] ?? 'External channel';
    }

    public static function channelPaymentVerified(Booking $stay): bool
    {
        return $stay->external_source !== null && ($stay->external_details['payment_arrangement'] ?? null) === 'channel_collects'
            && !empty($stay->external_details['guest_payment_verified_at']);
    }

    public static function guestPaymentCleared(Booking $stay): bool
    {
        return $stay->paid_at !== null || self::channelPaymentVerified($stay);
    }

    public static function balanceText(Booking $stay): ?string
    {
        if (!$stay->external_source) return null;
        if ($stay->status === 'cancelled') return 'Cancelled — any remaining charges or refunds require review';
        return match ($stay->external_details['payment_arrangement'] ?? null) {
            'channel_collects' => self::channelPaymentVerified($stay) ? null : 'Channel payment under review — do not pay again',
            'pay_at_property' => null,
            default => 'Payment arrangement under review — contact Guest Relations',
        };
    }

    public function create(array $input, User $staff): Booking
    {
        abort_unless($staff->isIplAdmin() || $staff->isDeliveryPartner() || $staff->isGuestRelations(), 403);
        $data = Validator::make($input, [
            'source' => 'required|in:'.implode(',', array_keys(self::SOURCES)), 'other_source_name' => 'required_if:source,other|nullable|string|max:30',
            'reference' => 'required|string|max:120', 'unit_reference' => 'required|string|max:40',
            'room_id' => 'required|integer|exists:rooms,id', 'check_in' => 'required|date_format:Y-m-d',
            'check_out' => 'required|date_format:Y-m-d|after:check_in|after:'.today(config('property.timezone'))->toDateString(),
            'guests' => 'required|integer|min:1|max:2', 'total' => 'required|numeric|decimal:0,2|min:0.01|max:99999999.99',
            'guest_name' => 'required|string|max:255', 'guest_phone' => 'nullable|string|max:40', 'guest_email' => 'nullable|email|max:255',
            'payment_arrangement' => 'required|in:'.implode(',', array_keys(self::ARRANGEMENTS)),
            'channel_terms' => 'required|string|max:4000', 'confirmation_checked' => 'required|accepted',
        ])->validate();
        $source = $data['source'] === 'other' ? 'other-'.\Illuminate\Support\Str::slug($data['other_source_name']) : $data['source'];
        if ($source === 'other-' || strlen($source) > 40) throw ValidationException::withMessages(['other_source_name' => 'Enter a short, recognisable channel name.']);
        $reference = strtoupper(trim($data['reference'])); $unit = strtoupper(trim($data['unit_reference']));
        if ($reference === '' || $unit === '') throw ValidationException::withMessages(['reference' => 'A channel reference and room-line reference are required.']);
        try {
            return DB::transaction(function () use ($data, $source, $reference, $unit, $staff) {
                RoomAvailability::lockAvailableRoom($data['room_id'], $data['check_in'], $data['check_out']);
                $stay = Booking::create([
                    'external_source' => $source, 'external_reference' => $reference, 'external_unit_reference' => $unit,
                    'external_details' => ['source_label' => $data['source'] === 'other' ? trim($data['other_source_name']) : self::SOURCES[$data['source']],
                        'payment_arrangement' => $data['payment_arrangement'], 'channel_terms' => trim($data['channel_terms']),
                        'recorded_by' => $staff->id, 'recorded_at' => now()->toIso8601String()],
                    'reserved_by' => $staff->id, 'room_id' => $data['room_id'], 'check_in' => $data['check_in'], 'check_out' => $data['check_out'],
                    'guests' => $data['guests'], 'total_price' => number_format((float) $data['total'], 2, '.', ''),
                    'status' => 'confirmed', 'hold_expires_at' => null, 'minimum_payment_kobo' => AssistedReservationPayments::kobo((string) $data['total']),
                    'payment_method' => match ($data['payment_arrangement']) { 'pay_at_property' => 'bank_transfer', 'channel_collects' => 'external_channel', default => 'external_review' },
                    'booking_contact' => ['name' => $data['guest_name'], 'phone' => $data['guest_phone'] ?? null, 'email' => $data['guest_email'] ?? null],
                ]);
                foreach (User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_IPL_ADMIN, User::ROLE_DELIVERY_PARTNER, User::ROLE_GUEST_RELATIONS])->get() as $recipient) {
                    $recipient->notify(new \App\Notifications\PaymentActivityNotification('External room reservation recorded', self::sourceLabel($stay).' · '.$reference.' · '.$stay->room->name.' · '.$stay->check_in.' to '.$stay->check_out.'. Payment remains separate from reservation confirmation.', $stay->id, 'confirmed'));
                }
                ReservationGuestCommunication::sendUpdate($stay, 'external_recorded');
                return $stay;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['reference' => 'This channel reservation and room line are already recorded. Open the existing booking instead.']);
        }
    }

    public function verifyGuestChannelPayment(Booking $booking, User $reviewer, string $proofPath, string $note): void
    {
        abort_unless(AssistedReservationPayments::canVerify($reviewer), 403);
        if (trim($note) === '' || mb_strlen($note) > 2000 || !str_starts_with($proofPath, 'external-channel-evidence/') || !\Illuminate\Support\Facades\Storage::disk('local')->exists($proofPath)) {
            throw ValidationException::withMessages(['evidence' => 'Private channel evidence and a verification note are required.']);
        }
        DB::transaction(function () use ($booking, $reviewer, $proofPath, $note) {
            $stay = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (!$stay->external_source || ($stay->external_details['payment_arrangement'] ?? null) !== 'channel_collects'
                || self::channelPaymentVerified($stay) || $stay->status !== 'confirmed' || $stay->checked_in_at || $stay->paid_at) {
                throw ValidationException::withMessages(['booking' => 'This reservation is not awaiting channel payment verification.']);
            }
            $stay->external_details = array_merge($stay->external_details, ['guest_payment_verified_at' => now()->toIso8601String(),
                'guest_payment_verified_by' => $reviewer->id, 'guest_payment_evidence' => $proofPath, 'verification_note' => trim($note)]);
            $stay->save();
            // This confirms the guest's channel payment, not a bank payout to
            // Insignia. Do not set paid_at or create a cash receipt/ledger entry.
            ReservationGuestCommunication::sendUpdate($stay, 'channel_guest_verified');
        }, 3);
    }

    public function setPaymentArrangement(Booking $booking, User $reviewer, string $mode, string $note): void
    {
        abort_unless($reviewer->isIplAdmin() || $reviewer->isDeliveryPartner(), 403);
        Validator::make(['mode' => $mode, 'note' => $note], ['mode' => 'required|in:pay_at_property,channel_collects', 'note' => 'required|string|max:2000'])->validate();
        DB::transaction(function () use ($booking, $reviewer, $mode, $note) {
            $stay = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (!$stay->external_source || $stay->status !== 'confirmed' || $stay->checked_in_at || $stay->paid_at
                || self::channelPaymentVerified($stay) || AssistedReservationPayments::entries($stay)->whereNull('rejected_at')->exists()) {
                throw ValidationException::withMessages(['booking' => 'Payment arrangements cannot be changed after payment evidence or check-in. Refer the record to owner finance.']);
            }
            $details = $stay->external_details;
            $details['arrangement_reviews'][] = ['previous' => $details['payment_arrangement'], 'new' => $mode, 'by' => $reviewer->id, 'at' => now()->toIso8601String(), 'note' => trim($note)];
            $details['payment_arrangement'] = $mode;
            $stay->external_details = $details;
            $stay->payment_method = $mode === 'pay_at_property' ? 'bank_transfer' : 'external_channel';
            $stay->save();
        }, 3);
    }

    public function cancel(Booking $booking, User $reviewer, string $note): void
    {
        abort_unless($reviewer->isIplAdmin() || $reviewer->isDeliveryPartner(), 403);
        Validator::make(['note' => $note], ['note' => 'required|string|min:10|max:2000'])->validate();
        DB::transaction(function () use ($booking, $reviewer, $note) {
            \App\Models\Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            $stay = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if (!$stay->external_source || $stay->status !== 'confirmed' || $stay->checked_in_at || $stay->checked_out_at) {
                throw ValidationException::withMessages(['booking' => 'Only an external reservation that has not checked in can be cancelled here.']);
            }
            if (AssistedReservationPayments::pending($stay)->exists()) {
                throw ValidationException::withMessages(['booking' => 'Verify or reject pending property payment evidence before recording a cancellation.']);
            }
            if (!$reviewer->isIplAdmin() && ($stay->paid_at || AssistedReservationPayments::verified($stay) > 0)) {
                throw ValidationException::withMessages(['booking' => 'Owner finance must review cancellation when property payment or payment evidence exists.']);
            }
            $stay->external_details = array_merge($stay->external_details, ['cancellation_review' => ['by' => $reviewer->id, 'at' => now()->toIso8601String(), 'note' => trim($note)]]);
            $stay->status = 'cancelled';
            $stay->save();
            // Payment evidence and received money are retained. No refund is
            // inferred, no bank payout is made and no physical room is released.
            ReservationGuestCommunication::sendUpdate($stay, 'external_cancelled');
        }, 3);
    }
}
