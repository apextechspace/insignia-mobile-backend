<?php

namespace App\Filament\Operations\Resources\MonthlyStatementResource\Pages;

use App\Filament\Operations\Resources\MonthlyStatementResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateMonthlyStatement extends CreateRecord
{
    protected static string $resource = MonthlyStatementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['submitted_by'] = Auth::id();
        $data['statement_code'] = 'STM-' . $data['year'] . '-' . str_pad((string) $data['month'], 2, '0', STR_PAD_LEFT);
        return $data;
    }
}
