<?php

namespace App\Services;

use App\Models\AccommodationLedger;
use App\Models\Expense;
use App\Models\MonthlyStatement;
use App\Models\PettyCashTransaction;
use Illuminate\Support\Carbon;

class MclStatementReport
{
    public function build(MonthlyStatement $statement): array
    {
        $local = Carbon::create((int) $statement->year, (int) $statement->month, 1, 0, 0, 0, config('property.timezone'));
        $start = $local->copy()->utc();
        $end = $local->copy()->addMonth()->utc();
        $ledger = AccommodationLedger::withoutAssistedRevenue()->where('transaction_date', '>=', $start)->where('transaction_date', '<', $end)
            ->where(fn ($query) => $query->where('status', 'confirmed')
                ->orWhere(fn ($refund) => $refund->where('type', 'refund')->where('status', 'refunded')))
            ->orderBy('transaction_date')->orderBy('id')->get();
        $ledger = $ledger->concat(AssistedReceiptRows::between($start, $end))->sortBy('transaction_date');
        $expenses = Expense::where('status', 'approved')->where('currency', 'NGN')->where('financial_stream', 'accommodation')
            ->where('expense_date', '>=', $start)->where('expense_date', '<', $end)
            ->orderBy('expense_date')->orderBy('id')->get();
        $float = PettyCashTransaction::where('transaction_date', '<', $end)->orderBy('transaction_date')->orderBy('id')->get();
        $cents = fn ($amount): int => (int) round((float) $amount * 100);
        $rows = [];
        $days = [];
        $categories = [];
        $receipts = $refunds = $adjustments = $expenseTotal = $opening = $topups = $spending = $returned = $balance = 0;

        foreach ($ledger as $entry) {
            $amount = $cents($entry->net_amount);
            $date = $entry->transaction_date->copy()->timezone(config('property.timezone'))->toDateString();
            if ($entry->type === 'refund') {
                $refunds += abs($amount);
                $amount = -abs($amount);
            } elseif ($entry->type === 'adjustment') {
                $adjustments += $amount;
            } else {
                $receipts += $amount;
            }
            $days[$date]['revenue'] = ($days[$date]['revenue'] ?? 0) + $amount;
            $rows[] = [$date, $entry->type, $entry->reference, $amount];
        }

        foreach ($expenses as $expense) {
            $date = $expense->expense_date->copy()->timezone(config('property.timezone'))->toDateString();
            $amount = $cents($expense->amount);
            $expenseTotal += $amount;
            $categories[$expense->category] = ($categories[$expense->category] ?? 0) + $amount;
            $days[$date]['expenses'] = ($days[$date]['expenses'] ?? 0) + $amount;
            $rows[] = [$date, 'expense', 'EXP-'.$expense->id.' · '.$expense->title, -$amount];
        }

        $floatTrend = [$local->toDateString().' · Opening' => 0];
        foreach ($float as $entry) {
            $amount = $cents($entry->amount);
            $movement = $entry->type === 'replenishment' ? $amount : -$amount;
            $balance += $movement;
            if ($entry->transaction_date->lt($start)) {
                $opening += $movement;
                $floatTrend[$local->toDateString().' · Opening'] = $opening;

                continue;
            }
            if ($entry->type === 'replenishment') {
                $topups += $amount;
            } elseif ($entry->type === 'return') {
                $returned += $amount;
            } else {
                $spending += $amount;
            }
            $date = $entry->transaction_date->copy()->timezone(config('property.timezone'))->toDateString();
            $floatTrend[$date] = $balance;
            $rows[] = [$date, 'imprest '.$entry->type, 'FLOAT-'.$entry->id.' · '.$entry->purpose, $movement];
        }

        usort($rows, fn ($a, $b) => $a[0] <=> $b[0]);
        ksort($days);
        $revenue = $receipts - $refunds + $adjustments;
        $fee = (int) round(max(0, $revenue) * (float) $statement->management_fee_percentage / 100);
        $actual = $statement->actual_float_balance === null ? null : $cents($statement->actual_float_balance);
        $difference = $actual === null ? null : $actual - $balance;
        $net = $revenue - $expenseTotal - $fee;

        return compact('receipts', 'refunds', 'adjustments', 'revenue', 'expenseTotal', 'fee', 'net', 'opening', 'topups',
            'spending', 'returned', 'balance', 'actual', 'difference', 'rows', 'days', 'categories', 'floatTrend');
    }
}
