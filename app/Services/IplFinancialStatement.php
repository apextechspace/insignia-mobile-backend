<?php

namespace App\Services;

use App\Models\AccommodationLedger;
use App\Models\Expense;
use App\Models\FoodOrder;
use App\Models\Order;
use App\Models\ServiceBooking;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class IplFinancialStatement
{
    public const SOURCES = ['all', 'accommodation', 'dining', 'boutique', 'concierge', 'expense'];

    /**
     * An operating activity statement, not a bank balance. We count recorded
     * verified transactions once, and do not infer settlements from transfers.
     */
    public function report(string $from, string $to, string $source = 'all'): array
    {
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, config('property.timezone'));
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, config('property.timezone'));
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['dates' => 'Use valid YYYY-MM-DD dates.']);
        }

        if (! $start || ! $end || $start->format('Y-m-d') !== $from
            || $end->format('Y-m-d') !== $to || $start->greaterThan($end)
            || $start->diffInDays($end) > 366 || ! in_array($source, self::SOURCES, true)) {
            throw ValidationException::withMessages(['dates' => 'Choose a valid date range of 367 days or fewer and a supported source.']);
        }

        $start = $start->startOfDay()->utc();
        $exclusiveEnd = $end->addDay()->startOfDay()->utc();
        $rows = collect();
        $append = function (string $sourceName, string $type, int $id, $date, $amount, string $reference, string $method, string $detail) use ($rows, $source): void {
            if ($source !== 'all' && $sourceName !== $source) {
                return;
            }
            $cents = (int) round((float) $amount * 100);
            if ($cents <= 0) {
                return;
            }
            $rows->push([
                'key' => $sourceName.':'.$type.':'.$id,
                'date' => CarbonImmutable::parse($date)->timezone(config('property.timezone'))->format('Y-m-d H:i:s'),
                'source' => $sourceName,
                'type' => $type,
                'reference' => $reference,
                'method' => $method,
                'description' => $detail,
                'amount_cents' => in_array($type, ['refund', 'expense'], true) ? -$cents : $cents,
            ]);
        };

        // Accommodation revenue is represented by its canonical ledger, not
        // by Booking as well. An unledgered booking is a reconciliation exception.
        if ($source === 'all' || $source === 'accommodation') {
            AccommodationLedger::query()
                ->with('booking')->withoutAssistedRevenue()
                ->whereIn('type', ['revenue', 'refund'])
                ->whereIn('status', ['confirmed', 'refunded'])
                ->where('transaction_date', '>=', $start)->where('transaction_date', '<', $exclusiveEnd)
                ->orderBy('id')->chunk(200, function ($entries) use ($append): void {
                    foreach ($entries as $entry) {
                        if ($entry->type === 'revenue'
                            && (! $entry->booking || ! $entry->booking->paid_at)) {
                            continue;
                        }
                        // Refund rows are included only when the refund ledger
                        // and booking both explicitly indicate a completed refund.
                        // The statement still requires external evidence review.
                        if ($entry->type === 'refund' && (
                            $entry->status !== 'refunded'
                            || ! $entry->booking
                            || $entry->booking->status !== 'refunded'
                        )) {
                            continue;
                        }
                        $append('accommodation', $entry->type, $entry->id,
                            $entry->transaction_date, $entry->gross_amount,
                            $entry->reference, $entry->payment_method ?? 'unknown',
                            'Suite booking #'.($entry->booking_id ?? 'manual'));
                    }
                });
            foreach (AssistedReceiptRows::between($start, $exclusiveEnd) as $payment) {
                $append('accommodation', 'payment', $payment->id, $payment->transaction_date,
                    $payment->gross_amount, $payment->reference, $payment->payment_method,
                    'Verified accommodation payment for booking #'.$payment->booking_id);
            }
        }

        foreach ([
            ['dining', FoodOrder::class, 'total_amount'],
            ['boutique', Order::class, 'total_price'],
            ['concierge', ServiceBooking::class, 'total_price'],
        ] as [$category, $model, $field]) {
            if ($source !== 'all' && $source !== $category) {
                continue;
            }
            $model::query()->whereNotNull('paid_at')
                ->where('paid_at', '>=', $start)->where('paid_at', '<', $exclusiveEnd)
                ->orderBy('id')->chunk(200, function ($payments) use ($append, $category, $field): void {
                    foreach ($payments as $payment) {
                        $append($category, 'income', $payment->id, $payment->paid_at,
                            $payment->{$field}, $payment->flutterwave_reference
                                ?: ($payment->payment_reference ?: strtoupper($category).'-'.$payment->id),
                            $payment->payment_method ?? 'unknown',
                            ucfirst($category).' #'.$payment->id);
                    }
                });
        }

        if ($source === 'all' || $source === 'expense') {
            Expense::query()->where('status', 'approved')
                ->where('expense_date', '>=', $start)->where('expense_date', '<', $exclusiveEnd)
                ->orderBy('id')->chunk(200, function ($expenses) use ($append): void {
                    foreach ($expenses as $expense) {
                        $append('expense', 'expense', $expense->id, $expense->expense_date,
                            $expense->amount, 'EXP-'.$expense->id,
                            $expense->payment_method ?? 'unknown', $expense->title);
                    }
                });
        }

        $rows = $rows->sort(fn ($a, $b) => [$a['date'], $a['key']] <=> [$b['date'], $b['key']])->values();
        $balance = 0;
        $rows = $rows->map(function ($row) use (&$balance) {
            $balance += $row['amount_cents'];
            $row['running_cents'] = $balance;

            return $row;
        });

        return [
            'from' => $from,
            'to' => $to,
            'source' => $source,
            'rows' => $rows,
            'inflow_cents' => $rows->sum(fn ($row) => max(0, $row['amount_cents'])),
            'outflow_cents' => -$rows->sum(fn ($row) => min(0, $row['amount_cents'])),
            'net_cents' => $balance,
            'opening_cents' => 0,
        ];
    }
}
