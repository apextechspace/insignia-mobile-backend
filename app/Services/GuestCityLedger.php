<?php

namespace App\Services;

use App\Models\{Booking, GuestCityLedgerAccount, GuestCityLedgerEntry, Room, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuestCityLedger
{
    public const METHODS = ['pos' => 'POS terminal', 'bank_transfer' => 'Direct bank transfer', 'cash' => 'Cash'];

    public static function canManage(?User $user): bool
    {
        return $user !== null && ($user->isIplAdmin() || $user->isDeliveryPartner());
    }

    public static function balance(GuestCityLedgerAccount $account): int
    {
        return (int) $account->entries()->selectRaw("COALESCE(SUM(CASE WHEN type = 'credit' THEN amount_kobo ELSE -amount_kobo END), 0) AS balance")->value('balance');
    }

    public function open(array $data, User $staff): GuestCityLedgerAccount
    {
        abort_unless(self::canManage($staff), 403);
        $name = trim((string) ($data['guest_name'] ?? ''));
        if ($name === '') throw ValidationException::withMessages(['guest_name' => 'Enter the guest or company name.']);

        return GuestCityLedgerAccount::create([
            'user_id' => $data['user_id'] ?? null, 'guest_name' => $name,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'status' => 'active', 'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'account_reference' => 'CL-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'opened_by' => $staff->id,
        ]);
    }

    public function credit(GuestCityLedgerAccount $account, User $staff, int $amountKobo, string $method, string $reference, ?string $evidencePath, ?string $notes): GuestCityLedgerEntry
    {
        abort_unless(self::canManage($staff), 403);
        if ($account->status !== 'active') throw ValidationException::withMessages(['account' => 'Only an active City Ledger account can receive funds.']);
        if ($amountKobo < 1 || !isset(self::METHODS[$method]) || trim($reference) === '') throw ValidationException::withMessages(['amount' => 'Enter a valid amount, payment method and bank/POS reference.']);
        if (!$evidencePath || !str_starts_with($evidencePath, 'city-ledger-evidence/')) throw ValidationException::withMessages(['evidence' => 'Upload the POS or bank evidence before posting prepaid funds.']);

        return DB::transaction(function () use ($account, $staff, $amountKobo, $method, $reference, $evidencePath, $notes) {
            $account = GuestCityLedgerAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $reference = strtoupper(trim($reference));
            if (GuestCityLedgerEntry::whereRaw('UPPER(TRIM(reference)) = ?', [$reference])->exists()
                || DB::table('manual_payment_reviews')->whereRaw('UPPER(TRIM(bank_transaction_reference)) = ?', [$reference])->exists()) {
                throw ValidationException::withMessages(['reference' => 'This reference has already been used. Do not credit it twice.']);
            }
            return GuestCityLedgerEntry::create([
                'guest_city_ledger_account_id' => $account->id, 'type' => 'credit', 'amount_kobo' => $amountKobo,
                'reference' => $reference, 'payment_method' => $method, 'evidence_path' => $evidencePath,
                'notes' => trim((string) $notes) ?: null, 'entered_by' => $staff->id, 'posted_at' => now(),
            ]);
        }, 3);
    }

    public function applyToBooking(GuestCityLedgerAccount $account, Booking $booking, User $staff, ?string $notes = null): GuestCityLedgerEntry
    {
        abort_unless(self::canManage($staff), 403);
        return DB::transaction(function () use ($account, $booking, $staff, $notes) {
            $account = GuestCityLedgerAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $stay = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            Room::whereKey($stay->room_id)->lockForUpdate()->firstOrFail();
            if ($account->status !== 'active' || $stay->paid_at || $stay->checked_in_at || $stay->checked_out_at || !in_array($stay->status, ['pending', 'confirmed'], true)) {
                throw ValidationException::withMessages(['booking' => 'This account or reservation cannot be charged.']);
            }
            if ($stay->external_source && ($stay->external_details['payment_arrangement'] ?? null) !== 'pay_at_property') {
                throw ValidationException::withMessages(['booking' => 'Do not use City Ledger funds for a channel-collected reservation.']);
            }
            $outstanding = max(0, AssistedReservationPayments::kobo($stay->total_price) - AssistedReservationPayments::verified($stay));
            if ($outstanding < 1) throw ValidationException::withMessages(['booking' => 'This reservation has no eligible outstanding balance.']);
            if (GuestCityLedgerEntry::where('booking_id', $stay->id)->where('type', 'debit')->exists()) throw ValidationException::withMessages(['booking' => 'City Ledger funds were already applied to this booking.']);
            if (self::balance($account) < $outstanding) throw ValidationException::withMessages(['account' => 'Insufficient prepaid balance for this full reservation.']);
            if ($stay->status === 'pending'
                && RoomAvailability::blocking($stay->check_in, $stay->check_out, $stay->id)->where('room_id', $stay->room_id)->exists()) {
                throw ValidationException::withMessages(['booking' => 'The suite is no longer available for these dates. Do not debit the guest balance.']);
            }

            $entry = GuestCityLedgerEntry::create([
                'guest_city_ledger_account_id' => $account->id, 'booking_id' => $stay->id, 'type' => 'debit',
                'amount_kobo' => $outstanding, 'reference' => 'CL-USE-'.$stay->id.'-'.strtoupper(Str::random(6)),
                'payment_method' => 'city_ledger', 'notes' => trim((string) $notes) ?: 'Applied to accommodation booking #'.$stay->id,
                'entered_by' => $staff->id, 'posted_at' => now(),
            ]);
            $stay->forceFill([
                'status' => 'confirmed', 'hold_expires_at' => null, 'paid_at' => now(), 'payment_method' => 'city_ledger',
                'payment_reference' => $entry->reference,
            ])->save();
            return $entry;
        }, 3);
    }
}
