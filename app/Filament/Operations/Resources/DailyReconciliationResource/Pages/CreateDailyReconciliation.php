<?php

namespace App\Filament\Operations\Resources\DailyReconciliationResource\Pages;

use App\Filament\Operations\Resources\DailyReconciliationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDailyReconciliation extends CreateRecord
{
    protected static string $resource = DailyReconciliationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['submitted_by'] = Auth::id();
        $data['status'] = 'submitted';
        if (! Auth::user()?->isIplAdmin()) {
            $data['recorded_ancillary_cash'] = 0;
        }

        return $data;
    }
}
