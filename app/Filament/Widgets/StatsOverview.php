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
        // Revenue is money verified as collected, not bookings merely created or confirmed.
        $bookingRevenue = Booking::whereNotNull('paid_at')->where('paid_at', '>=', $thirtyDaysAgo)->sum('total_price');
        $serviceRevenue = ServiceBooking::whereNotNull('paid_at')->where('paid_at', '>=', $thirtyDaysAgo)->sum('total_price');
        $shopRevenue = Order::whereNotNull('paid_at')->where('paid_at', '>=', $thirtyDaysAgo)->sum('total_price');
        $diningRevenue = \App\Models\FoodOrder::whereNotNull('paid_at')->where('paid_at', '>=', $thirtyDaysAgo)->sum('total_amount');
        $totalRevenue = $bookingRevenue + $serviceRevenue + $shopRevenue + $diningRevenue;

        $pendingRoomBookings = Booking::where('status', 'pending')->count();
        $futureConfirmedBookings = Booking::whereNotNull('paid_at')
            ->whereIn('status', ['confirmed', 'paid'])
            ->whereDate('check_in', '>=', today())->count();

        // MCL Operations Statements awaiting IPL Review
        $pendingStatements = \App\Models\MonthlyStatement::where('status', 'submitted_to_ipl')->count();

        $totalRooms = \App\Models\Room::count();
        $occupiedRooms = \App\Models\Room::where('status', 'occupied')->count();
        $occupancyPct = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100) : 0;

        $receiptsWaitingForReview = Booking::where('status', 'pending')
            ->whereNull('paid_at')
            ->where('payment_method', 'bank_transfer')
            ->whereNotNull('receipt_url')
            ->count();

        return [
            Stat::make('Suite Occupancy', "{$occupiedRooms}/{$totalRooms} ({$occupancyPct}%)")
                ->description($occupiedRooms > 0 ? 'Suites currently occupied' : 'All suites vacant / ready')
                ->descriptionIcon('heroicon-m-home-modern', IconPosition::Before)
                ->color($occupiedRooms >= 3 ? 'success' : 'info'),
            Stat::make('Total Revenue (30d)', '₦' . number_format($totalRevenue))
                ->description('Room: ₦' . number_format($bookingRevenue) . ' · Ancillary: ₦' . number_format($serviceRevenue + $shopRevenue + $diningRevenue))
                ->descriptionIcon('heroicon-m-banknotes', IconPosition::Before)
                ->color('success'),
            Stat::make('Bank Receipts to Verify', $receiptsWaitingForReview)
                ->description($receiptsWaitingForReview ? 'Check actual bank credit before approval' : 'No pending transfer receipts')
                ->descriptionIcon('heroicon-m-shield-check', IconPosition::Before)
                ->color($receiptsWaitingForReview ? 'warning' : 'success'),
            Stat::make('MCL Statements', $pendingStatements)
                ->description($pendingStatements > 0 ? 'Monthly billing awaiting IPL approval' : 'All statements reviewed')
                ->descriptionIcon('heroicon-m-document-check', IconPosition::Before)
                ->color($pendingStatements > 0 ? 'danger' : 'success'),
            Stat::make('Pending Actions', ($pendingConcierge + $pendingOrders + $pendingRoomBookings))
                ->description("{$pendingRoomBookings} room bookings · {$pendingConcierge} concierge · {$pendingOrders} boutique")
                ->descriptionIcon('heroicon-m-sparkles', IconPosition::Before)
                ->color(($pendingConcierge + $pendingOrders + $pendingRoomBookings) > 0 ? 'warning' : 'success'),
        ];
    }
}
