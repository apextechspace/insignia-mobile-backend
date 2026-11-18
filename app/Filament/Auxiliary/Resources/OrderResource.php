<?php

namespace App\Filament\Auxiliary\Resources;

class OrderResource extends FulfilmentResource
{
    protected static ?string $model = \App\Models\Order::class;

    protected static ?string $navigationLabel = 'Shop Orders';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    public static function transitions(): array
    {
        return ['confirmed' => 'processing', 'processing' => 'shipped', 'shipped' => 'delivered'];
    }

    public static function getPages(): array
    {
        return ['index' => OrderResource\Pages\ListOrders::route('/')];
    }
}
