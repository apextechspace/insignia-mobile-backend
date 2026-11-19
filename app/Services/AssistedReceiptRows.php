<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AssistedReceiptRows
{
    // Receipt-date reporting: verification is the point at which a receipt is posted.
    // These virtual rows do not write another ledger entry or mutate existing money.
    public static function between($start, $exclusiveEnd): \Illuminate\Support\Collection
    {
        return DB::table('assisted_reservation_payments')->whereNotNull('verified_at')
            ->where('verified_at', '>=', $start)->where('verified_at', '<', $exclusiveEnd)
            ->orderBy('verified_at')->orderBy('id')->get()->map(fn ($payment) => (object) [
                'id' => $payment->id, 'booking_id' => $payment->booking_id,
                'type' => 'payment', 'reference' => $payment->reference,
                'gross_amount' => $payment->amount_kobo / 100,
                'net_amount' => $payment->amount_kobo / 100,
                'transaction_date' => Carbon::parse($payment->verified_at),
                'payment_method' => $payment->method,
            ]);
    }
}
