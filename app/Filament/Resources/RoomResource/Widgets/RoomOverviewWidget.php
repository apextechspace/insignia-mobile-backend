<?php

namespace App\Filament\Resources\RoomResource\Widgets;

use App\Models\Room;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RoomOverviewWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $totalRooms = Room::count();
        $suitesCount = Room::where('type', 'Suite')->count();
        $execCount = Room::where('type', 'Executive Room')->count();
        $standardCount = Room::where('type', 'Standard Room')->count();

        $readyRooms = Room::where('status', 'available')->where('is_available', true)->count();
        $turnoverRooms = Room::where('status', 'cleaning')->count();
        $maintenanceRooms = Room::where('status', 'maintenance')->count();
        $occupiedRooms = Room::where('status', 'occupied')->count();

        $occupancyPct = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100) : 0;
        $avgPrice = $totalRooms > 0 ? round(Room::avg('price_per_night')) : 0;

        return [
            Stat::make('Total Accommodation Units', $totalRooms)
                ->description("{$suitesCount} Suites · {$execCount} Executive · {$standardCount} Standard")
                ->descriptionIcon('heroicon-m-home-modern', IconPosition::Before)
                ->color('primary'),

            Stat::make('Ready for Check-in', "{$readyRooms} / {$totalRooms}")
                ->description('Turnkey inspected & published online')
                ->descriptionIcon('heroicon-m-check-badge', IconPosition::Before)
                ->color($readyRooms > 0 ? 'success' : 'warning'),

            Stat::make('Turnover & Maintenance', ($turnoverRooms + $maintenanceRooms))
                ->description("{$turnoverRooms} Cleaning · {$maintenanceRooms} Maintenance")
                ->descriptionIcon('heroicon-m-sparkles', IconPosition::Before)
                ->color(($turnoverRooms + $maintenanceRooms) > 0 ? 'warning' : 'success'),

            Stat::make('Current Occupancy', "{$occupiedRooms} ({$occupancyPct}%)")
                ->description("Avg rate: ₦" . number_format($avgPrice) . " / night")
                ->descriptionIcon('heroicon-m-user-group', IconPosition::Before)
                ->color($occupiedRooms > 0 ? 'info' : 'gray'),
        ];
    }
}

