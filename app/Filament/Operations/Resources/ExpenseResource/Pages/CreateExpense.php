<?php

namespace App\Filament\Operations\Resources\ExpenseResource\Pages;

use App\Filament\Operations\Resources\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['logged_by'] = Auth::id();
        $data['status'] = 'pending_approval';
        return $data;
    }
}
