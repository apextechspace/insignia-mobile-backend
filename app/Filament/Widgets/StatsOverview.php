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
        
        $thirtyDaysAgo = now()->subDays(30);
        $bookingRevenue = Booking::where('status', 'confirmed')->where('created_at', '>=', $thirtyDaysAgo)->sum('total_price');
        $serviceRevenue = ServiceBooking::whereIn('status', ['confirmed', 'completed'])->where('created_at', '>=', $thirtyDaysAgo)->sum('total_price');
        $shopRevenue = Order::whereIn('status', ['confirmed', 'completed', 'delivered'])->where('created_at', '>=', $thirtyDaysAgo)->sum('total_price');
        $totalRevenue = $bookingRevenue + $serviceRevenue + $shopRevenue;

        // MCL Operations Statements awaiting IPL Review
        $pendingStatements = \App\Models\MonthlyStatement::where('status', 'submitted_to_ipl')->count();

        $totalRooms = \App\Models\Room::count() ?: 6;
        $occupiedRooms = \App\Models\Room::where('status', 'occupied')->count();
        $occupancyPct = round(($occupiedRooms / $totalRooms) * 100);

        return [
            Stat::make('Suite Occupancy', "{$occupiedRooms}/{$totalRooms} ({$occupancyPct}%)")
                ->description($occupiedRooms > 0 ? 'Live occupied suites at Wuse II' : 'All suites vacant / ready')
                ->descriptionIcon('heroicon-m-home-modern', IconPosition::Before)
                ->color($occupiedRooms >= 3 ? 'success' : 'info'),
            Stat::make('Total Revenue (30d)', '₦' . number_format($totalRevenue))
                ->description('Room: ₦' . number_format($bookingRevenue) . ' · Ancillary: ₦' . number_format($serviceRevenue + $shopRevenue))
                ->descriptionIcon('heroicon-m-banknotes', IconPosition::Before)
                ->color('success'),
            Stat::make('MCL Statements', $pendingStatements)
                ->description($pendingStatements > 0 ? 'Monthly billing awaiting IPL approval' : 'All statements reviewed')
                ->descriptionIcon('heroicon-m-document-check', IconPosition::Before)
                ->color($pendingStatements > 0 ? 'danger' : 'success'),
            Stat::make('Concierge & Boutique', ($pendingConcierge + $pendingOrders))
                ->description("{$pendingConcierge} service · {$pendingOrders} boutique orders")
                ->descriptionIcon('heroicon-m-sparkles', IconPosition::Before)
                ->color(($pendingConcierge + $pendingOrders) > 0 ? 'warning' : 'success'),
        ];
    }
}
