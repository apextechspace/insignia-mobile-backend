<?php

namespace App\Filament\Operations\Resources\AssetResource\Pages;

use App\Filament\Operations\Resources\AssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Register New Asset'),
        ];
    }
}
