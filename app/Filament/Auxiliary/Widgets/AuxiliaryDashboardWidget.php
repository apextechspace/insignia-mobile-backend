<?php

namespace App\Filament\Auxiliary\Widgets;

use App\Filament\Auxiliary\Resources\FoodOrderResource;
use App\Filament\Auxiliary\Resources\OrderResource;
use App\Filament\Auxiliary\Resources\ServiceBookingResource;
use App\Models\FoodOrder;
use App\Models\Order;
use App\Models\ServiceBooking;
use Filament\Widgets\Widget;

class AuxiliaryDashboardWidget extends Widget
{
    protected static string $view = 'filament.auxiliary.dashboard';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return ['queues' => [
            ['Kitchen', 'Prepare and deliver guest meals', FoodOrder::whereNotNull('paid_at')->whereIn('status', ['paid', 'preparing'])->count(), FoodOrderResource::getUrl(panel: 'auxiliary'), 'heroicon-o-cake'],
            ['Shop', 'Pack and deliver purchased items', Order::whereNotNull('paid_at')->whereIn('status', ['confirmed', 'processing', 'shipped'])->count(), OrderResource::getUrl(panel: 'auxiliary'), 'heroicon-o-shopping-bag'],
            ['Services & Coworking', 'Coordinate requests, errands and scheduled services', ServiceBooking::whereNotNull('paid_at')->whereIn('status', ['confirmed', 'in-progress'])->count(), ServiceBookingResource::getUrl(panel: 'auxiliary'), 'heroicon-o-bell'],
        ]];
    }
}
