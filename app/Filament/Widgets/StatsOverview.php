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

        // Use the same receipt-date accounting as the statement: deposits count
        // when verified, and full settlement must not count those payments twice.
        $today = today(config('property.timezone'));
        $receipts = app(\App\Services\IplFinancialStatement::class)->report($today->copy()->subDays(29)->toDateString(), $today->toDateString())['rows']
            ->filter(fn ($row) => $row['amount_cents'] > 0);
        $bookingRevenue = $receipts->where('source', 'accommodation')->sum('amount_cents') / 100;
        $serviceRevenue = $receipts->where('source', 'concierge')->sum('amount_cents') / 100;
        $shopRevenue = $receipts->where('source', 'boutique')->sum('amount_cents') / 100;
        $diningRevenue = $receipts->where('source', 'dining')->sum('amount_cents') / 100;
        $totalRevenue = $bookingRevenue + $serviceRevenue + $shopRevenue + $diningRevenue;

        $pendingRoomBookings = Booking::actionablePending()->count();
        $futureConfirmedBookings = Booking::whereNotNull('paid_at')
            ->whereIn('status', ['confirmed', 'paid'])
            ->whereDate('check_in', '>=', today())->count();

        // VRL Operations Statements awaiting IPL Review
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
            Stat::make('Verified Receipts (30d)', '₦'.number_format($totalRevenue, 2))
                ->description('Room: ₦'.number_format($bookingRevenue, 2).' · Ancillary: ₦'.number_format($serviceRevenue + $shopRevenue + $diningRevenue, 2))
                ->descriptionIcon('heroicon-m-banknotes', IconPosition::Before)
                ->color('success'),
            Stat::make('Bank Receipts to Verify', $receiptsWaitingForReview)
                ->description($receiptsWaitingForReview ? 'Check actual bank credit before approval' : 'No pending transfer receipts')
                ->descriptionIcon('heroicon-m-shield-check', IconPosition::Before)
                ->color($receiptsWaitingForReview ? 'warning' : 'success'),
            Stat::make('VRL Statements', $pendingStatements)
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
