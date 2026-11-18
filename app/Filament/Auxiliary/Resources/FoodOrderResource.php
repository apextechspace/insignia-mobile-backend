<?php

namespace App\Filament\Auxiliary\Resources;

class FoodOrderResource extends FulfilmentResource
{
    protected static ?string $model = \App\Models\FoodOrder::class;

    protected static ?string $navigationLabel = 'Kitchen Orders';

    protected static ?string $navigationIcon = 'heroicon-o-cake';

    public static function transitions(): array
    {
        return ['paid' => 'preparing', 'preparing' => 'delivered'];
    }

    public static function getPages(): array
    {
        return ['index' => FoodOrderResource\Pages\ListFoodOrders::route('/')];
    }
}
