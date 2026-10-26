<?php

namespace App\Filament\Operations\Resources\AssetHandoverResource\Pages;

use App\Filament\Operations\Resources\AssetHandoverResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssetHandovers extends ListRecords
{
    protected static string $resource = AssetHandoverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Initiate Handover Protocol'),
        ];
    }
}
