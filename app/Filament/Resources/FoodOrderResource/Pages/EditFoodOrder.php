<?php

namespace App\Filament\Resources\FoodOrderResource\Pages;

use App\Filament\Resources\FoodOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFoodOrder extends EditRecord
{
    protected static string $resource = FoodOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
