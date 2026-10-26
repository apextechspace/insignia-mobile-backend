<?php

namespace App\Filament\Operations\Resources\ConsumableItemResource\Pages;

use App\Filament\Operations\Resources\ConsumableItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConsumableItems extends ListRecords
{
    protected static string $resource = ConsumableItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
