<?php

namespace App\Filament\Operations\Resources\PettyCashResource\Pages;

use App\Filament\Operations\Resources\PettyCashResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPettyCashTransactions extends ListRecords
{
    protected static string $resource = PettyCashResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Record Cash Transaction'),
        ];
    }
}
