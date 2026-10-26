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

        if ($data['type'] === 'replenishment') {
            $data['balance_after'] = $lastBalance + (float) $data['amount'];
        } else {
            $data['balance_after'] = max(0, $lastBalance - (float) $data['amount']);
        }

        return $data;
    }
}
