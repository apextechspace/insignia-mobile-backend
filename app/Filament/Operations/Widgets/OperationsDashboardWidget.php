<?php

namespace App\Filament\Operations\Widgets;

use App\Models\Booking;
use App\Models\Incident;
use App\Models\Room;
use App\Models\StaffTask;
use App\Models\StaffShift;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class OperationsDashboardWidget extends Widget
{
    protected static string $view = 'filament.operations.widgets.operations-dashboard';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public function getViewData(): array
    {
        $user = Auth::user();
        $propertyNow = now(config('property.timezone'));
        $propertyDate = $propertyNow->toDateString();
        if ($user?->isStaff()) {
            return [
                'staffTasks' => StaffTask::with(['room', 'propertyArea'])
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->latest('id')->get(),
                'staffShifts' => StaffShift::where('user_id', $user->id)
                    ->whereDate('shift_date', '>=', $propertyDate)
                    ->orderBy('shift_date')->limit(3)->get(),
            ];
        }

        if ($user?->isGuestRelations()) {
            $stays = Booking::with(['room', 'user'])->whereIn('status', ['confirmed', 'paid', 'completed']);

            return [
                'propertyNow' => $propertyNow,
                'arrivals' => (clone $stays)->whereDate('check_in', $propertyDate)
                    ->whereIn('status', ['confirmed', 'paid'])
                    ->whereNull('checked_in_at')->whereNull('checked_out_at')->orderBy('id')->get(),
                'inHouse' => (clone $stays)->whereNotNull('checked_in_at')->whereNull('checked_out_at')
                    ->orderBy('check_out')->get(),
                'paymentReviews' => Booking::with(['room', 'user'])->actionablePending()
                    ->where('payment_method', 'bank_transfer')->whereNull('paid_at')
                    ->whereDate('check_in', '<=', $propertyDate)->orderBy('check_in')->get(),
                'openIssues' => Incident::whereNotIn('status', ['resolved', 'closed'])
                    ->with('assignedTo')->latest('id')->limit(5)->get(),
                'handoverShifts' => StaffShift::with('user')
                    ->whereHas('user', fn ($query) => $query->where('role', \App\Models\User::ROLE_GUEST_RELATIONS))
                    ->whereNotNull('handover_notes')->where('handover_notes', '!=', '')
                    ->latest('shift_date')->latest('id')->limit(3)->get(),
            ];
        }

        $rooms = Room::with(['activeBooking.user'])->orderBy('name')->get();

        // KPI Stats
        $occupiedCount = $rooms->where('status', 'occupied')->count();
        $cleaningCount = $rooms->where('status', 'cleaning')->count();
        $maintenanceCount = $rooms->where('status', 'maintenance')->count();
        $availableCount = $rooms->where('status', 'available')->count();

        $todayArrivals = Booking::whereDate('check_in', $propertyDate)
            ->whereIn('status', ['confirmed', 'paid'])
            ->count();
        $todayDepartures = Booking::whereDate('check_out', $propertyDate)
            ->whereIn('status', ['confirmed', 'paid', 'completed'])
            ->count();

        // Bookings and physical housekeeping state are distinct. All panels
        // query the same booking records, including receipt-pending bookings.
        $pendingReservations = Booking::with(['room', 'user'])
            ->actionablePending()
            ->orderBy('check_in')
            ->limit(12)->get();

        $upcomingReservations = Booking::with(['room', 'user'])
            ->whereIn('status', ['confirmed', 'paid'])
            ->whereNotNull('paid_at')
            ->whereDate('check_in', '>=', $propertyDate)
            ->orderBy('check_in')
            ->limit(12)->get();

        $openIncidents = Incident::whereNotIn('status', ['resolved', 'closed'])->count();
        $pendingTasks = StaffTask::whereIn('status', ['pending', 'in_progress'])->count();

        return [
            'propertyNow' => $propertyNow,
            'imprestBalance' => \App\Models\PettyCashTransaction::latest('id')->value('balance_after') ?? 0,
            'cleaningReviews' => StaffTask::with(['room', 'propertyArea'])
                ->where('status', 'completed')
                ->where(fn ($query) => $query->where('task_type', 'room_turnaround')->orWhereNotNull('property_area_id'))
                ->latest('id')->limit(6)->get(),
            'urgentIncidents' => Incident::whereNotIn('status', ['resolved', 'closed'])
                ->whereIn('severity', ['high', 'critical'])->latest('id')->limit(6)->get(),
            'rooms' => $rooms,
            'occupiedCount' => $occupiedCount,
            'availableCount' => $availableCount,
            'cleaningCount' => $cleaningCount,
            'maintenanceCount' => $maintenanceCount,
            'todayArrivals' => $todayArrivals,
            'todayDepartures' => $todayDepartures,
            'openIncidents' => $openIncidents,
            'pendingTasks' => $pendingTasks,
            'user' => $user,
            'pendingReservations' => $pendingReservations,
            'upcomingReservations' => $upcomingReservations,
        ];
    }
}
