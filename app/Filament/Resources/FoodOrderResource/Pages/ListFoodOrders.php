<?php

namespace App\Filament\Resources\FoodOrderResource\Pages;

use App\Filament\Resources\FoodOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFoodOrders extends ListRecords
{
    protected static string $resource = FoodOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
