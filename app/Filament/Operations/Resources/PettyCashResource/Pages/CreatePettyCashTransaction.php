<?php

namespace App\Filament\Operations\Resources\PettyCashResource\Pages;

use App\Filament\Operations\Resources\PettyCashResource;
use App\Models\PettyCashTransaction;
use App\Models\Expense;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePettyCashTransaction extends CreateRecord
{
    protected static string $resource = PettyCashResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['handled_by'] = auth()->id();
        $data['approved_by'] = auth()->id();
        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        if (!auth()->user()?->isIplAdmin()) {
            abort(403);
        }

        return DB::transaction(function () use ($data): PettyCashTransaction {
            // A stable existing row serializes the float even when it is empty.
            // Every float write must use this same path to retain the guarantee.
            User::query()->orderBy('id')->lockForUpdate()->firstOrFail();

            $amount = (int) round((float) ($data['amount'] ?? 0) * 100);
            if ($amount < 1 || $amount > 999999999999) {
                throw ValidationException::withMessages(['data.amount' => 'Enter a valid NGN amount.']);
            }
            $type = $data['type'] ?? null;
            if (!in_array($type, ['replenishment', 'disbursement', 'return'], true)) {
                throw ValidationException::withMessages(['data.type' => 'Invalid operating float transaction.']);
            }

            $balance = (int) round((float) (PettyCashTransaction::latest('id')->value('balance_after') ?? 0) * 100);
            if ($type === 'replenishment' && $balance + $amount > 999999999999) {
                throw ValidationException::withMessages(['data.amount' => 'This top-up would exceed the maximum ledger balance.']);
            }
            if ($type !== 'replenishment' && $amount > $balance) {
                throw ValidationException::withMessages(['data.amount' => 'Insufficient operating float for this transaction.']);
            }
            if ($type === 'disbursement') {
                $expense = Expense::whereKey($data['expense_id'] ?? null)->where('status', 'approved')
                    ->where('currency', 'NGN')->lockForUpdate()->first();
                if (! $expense || (int) round((float) $expense->amount * 100) !== $amount
                    || PettyCashTransaction::where('expense_id', $expense->id)->exists()) {
                    throw ValidationException::withMessages(['data.expense_id' => 'Choose an approved, unused expense for the same amount.']);
                }
            } else {
                $data['expense_id'] = null;
            }

            $data['handled_by'] = auth()->id();
            $data['approved_by'] = auth()->id();
            $data['amount'] = $amount / 100;
            $data['balance_after'] = ($type === 'replenishment' ? $balance + $amount : $balance - $amount) / 100;

            return PettyCashTransaction::create($data);
        }, 3);
    }
}
