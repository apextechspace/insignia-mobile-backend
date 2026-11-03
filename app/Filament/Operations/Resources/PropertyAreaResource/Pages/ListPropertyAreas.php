<?php

namespace App\Filament\Operations\Resources\PropertyAreaResource\Pages;

use App\Filament\Operations\Resources\PropertyAreaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPropertyAreas extends ListRecords
{
    protected static string $resource = PropertyAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
