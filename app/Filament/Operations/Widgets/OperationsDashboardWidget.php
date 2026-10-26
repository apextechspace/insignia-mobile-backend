<?php

namespace App\Filament\Operations\Widgets;

use App\Models\Room;
use App\Models\Incident;
use App\Models\StaffTask;
use App\Models\Booking;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class OperationsDashboardWidget extends Widget
{
    protected static string $view = 'filament.operations.widgets.operations-dashboard';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public function getViewData(): array
    {
        $rooms = Room::with(['activeBooking.user'])->orderBy('name')->get();
        $user  = Auth::user();

        // KPI Stats
        $occupiedCount    = $rooms->where('status', 'occupied')->count();
        $cleaningCount    = $rooms->where('status', 'cleaning')->count();
        $maintenanceCount = $rooms->where('status', 'maintenance')->count();
        $availableCount   = $rooms->where('status', 'available')->count();

        $todayArrivals  = Booking::whereDate('check_in', today())
            ->whereIn('status', ['confirmed', 'paid'])
            ->count();
        $todayDepartures = Booking::whereDate('check_out', today())
            ->whereIn('status', ['confirmed', 'paid', 'completed'])
            ->count();

        // Bookings and physical housekeeping state are distinct. All panels
        // query the same booking records, including receipt-pending bookings.
        $pendingReservations = Booking::with(['room', 'user'])
            ->where('status', 'pending')
            ->orderBy('check_in')
            ->limit(12)->get();

        $upcomingReservations = Booking::with(['room', 'user'])
            ->whereIn('status', ['confirmed', 'paid'])
            ->whereNotNull('paid_at')
            ->whereDate('check_in', '>=', today())
            ->orderBy('check_in')
            ->limit(12)->get();

        $openIncidents = Incident::whereNotIn('status', ['resolved', 'closed'])->count();
        $pendingTasks  = StaffTask::whereIn('status', ['pending', 'in_progress'])->count();

        return [
            'rooms'           => $rooms,
            'occupiedCount'   => $occupiedCount,
            'availableCount'  => $availableCount,
            'cleaningCount'   => $cleaningCount,
            'maintenanceCount'=> $maintenanceCount,
            'todayArrivals'   => $todayArrivals,
            'todayDepartures' => $todayDepartures,
            'openIncidents'   => $openIncidents,
            'pendingTasks'    => $pendingTasks,
            'user'            => $user,
            'pendingReservations' => $pendingReservations,
            'upcomingReservations' => $upcomingReservations,
        ];
    }
}
