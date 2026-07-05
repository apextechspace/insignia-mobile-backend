<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Order;
use App\Models\ServiceBooking;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $pendingConcierge = ServiceBooking::where('status', 'pending')->count();
        $pendingOrders = Order::where('status', 'pending')->count();
        
        // Calculate 30-day revenue across all streams
        $thirtyDaysAgo = now()->subDays(30);
        $bookingRevenue = Booking::where('status', 'confirmed')->where('created_at', '>=', $thirtyDaysAgo)->sum('total_price');
        $serviceRevenue = ServiceBooking::whereIn('status', ['confirmed', 'completed'])->where('created_at', '>=', $thirtyDaysAgo)->sum('total_price');
        $shopRevenue = Order::whereIn('status', ['confirmed', 'completed', 'delivered'])->where('created_at', '>=', $thirtyDaysAgo)->sum('total_price');
        $totalRevenue = $bookingRevenue + $serviceRevenue + $shopRevenue;

        return [
            Stat::make('Concierge Queue', $pendingConcierge)
                ->description('Pending service requests')
                ->descriptionIcon('heroicon-m-sparkles', IconPosition::Before)
                ->color($pendingConcierge > 0 ? 'warning' : 'success'),
            Stat::make('Boutique Queue', $pendingOrders)
                ->description('Orders awaiting fulfillment')
                ->descriptionIcon('heroicon-m-shopping-bag', IconPosition::Before)
                ->color($pendingOrders > 0 ? 'info' : 'success'),
            Stat::make('Elite Revenue (30d)', '₦' . number_format($totalRevenue))
                ->description('Combined ecosystem revenue')
                ->descriptionIcon('heroicon-m-banknotes', IconPosition::Before)
                ->color('success'),
        ];
    }
}
