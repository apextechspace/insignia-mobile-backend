<?php

namespace App\Filament\Operations\Resources\PettyCashResource\Pages;

use App\Filament\Operations\Resources\PettyCashResource;
use App\Models\PettyCashTransaction;
use Filament\Resources\Pages\CreateRecord;

class CreatePettyCashTransaction extends CreateRecord
{
    protected static string $resource = PettyCashResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $lastBalance = (float) (PettyCashTransaction::latest('id')->value('balance_after') ?? 0);

        $amount = (float) ($data['amount'] ?? 0);
        if (!is_finite($amount) || $amount <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'Enter a positive float amount.']);
        }
        if (!in_array($data['type'] ?? null, ['replenishment', 'disbursement'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['type' => 'Invalid transaction type.']);
        }
        if (($data['type'] ?? null) === 'disbursement' && $amount > $lastBalance) {
            throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'Disbursement exceeds available operating float.']);
        }
        $data['handled_by'] = auth()->id();
        $data['approved_by'] = auth()->id();

        if ($data['type'] === 'replenishment') {
            $data['balance_after'] = $lastBalance + (float) $data['amount'];
        } else {
            $data['balance_after'] = $lastBalance - $amount;
        }

        return $data;
    }
}
