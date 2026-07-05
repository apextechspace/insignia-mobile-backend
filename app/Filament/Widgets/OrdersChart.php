<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Booking;
use Filament\Widgets\ChartWidget;

class OrdersChart extends ChartWidget
{
    protected static ?string $heading = 'Residency Activity Trends (Weekly)';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $orders = [];
        $bookings = [];
        $services = [];
        $labels = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('D');
            
            $orders[] = \App\Models\Order::whereDate('created_at', $date->toDateString())->count();
            $bookings[] = \App\Models\Booking::whereDate('created_at', $date->toDateString())->count();
            $services[] = \App\Models\ServiceBooking::whereDate('created_at', $date->toDateString())->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Boutique Orders',
                    'data' => $orders,
                    'borderColor' => '#3498DB', 
                    'backgroundColor' => 'rgba(52, 152, 219, 0.1)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Suite Bookings',
                    'data' => $bookings,
                    'borderColor' => '#27AE60', 
                    'backgroundColor' => 'rgba(39, 174, 96, 0.1)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Concierge Requests',
                    'data' => $services,
                    'borderColor' => '#6B2D5A', // Insignia Plum
                    'backgroundColor' => 'rgba(107, 45, 90, 0.1)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
