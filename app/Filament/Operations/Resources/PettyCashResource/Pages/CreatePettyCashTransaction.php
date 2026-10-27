<?php

namespace App\Filament\Operations\Resources\PettyCashResource\Pages;

use App\Filament\Operations\Resources\PettyCashResource;
use App\Models\PettyCashTransaction;
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

            $amount = (float) ($data['amount'] ?? 0);
            if (!is_finite($amount) || $amount < 100) {
                throw ValidationException::withMessages(['amount' => 'Enter a valid amount of at least NGN 100.']);
            }
            $type = $data['type'] ?? null;
            if (!in_array($type, ['replenishment', 'disbursement'], true)) {
                throw ValidationException::withMessages(['type' => 'Invalid operating float transaction.']);
            }

            $balance = (float) (PettyCashTransaction::latest('id')->value('balance_after') ?? 0);
            if ($type === 'disbursement' && $amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'Insufficient operating float for this disbursement.']);
            }

            $data['handled_by'] = auth()->id();
            $data['approved_by'] = auth()->id();
            $data['balance_after'] = $type === 'replenishment'
                ? $balance + $amount : $balance - $amount;

            return PettyCashTransaction::create($data);
        }, 3);
    }
}
