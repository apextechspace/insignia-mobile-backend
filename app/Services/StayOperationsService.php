<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StayOperationsService
{
    private function authorize(User $staff): void
    {
        if (!in_array($staff->role, [
            User::ROLE_ADMIN, User::ROLE_IPL_ADMIN,
            User::ROLE_DELIVERY_PARTNER, User::ROLE_GUEST_RELATIONS,
        ], true)) {
            throw ValidationException::withMessages(['staff' => 'You cannot process guest arrivals and departures.']);
        }
    }

    public function checkIn(Booking $booking, User $staff): void
    {
        $this->authorize($staff);

        DB::transaction(function () use ($booking) {
            $room = Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            $stay = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (!$stay->paid_at || !in_array($stay->status, ['confirmed', 'paid'], true)
                || $stay->checked_in_at || $stay->checked_out_at) {
                throw ValidationException::withMessages(['booking' => 'A confirmed paid reservation is required for check-in.']);
            }
            if (today()->toDateString() < $stay->check_in || today()->toDateString() >= $stay->check_out) {
                throw ValidationException::withMessages(['booking' => 'Check-in is outside the booked stay dates.']);
            }
            if (!in_array($room->status, ['available', 'reserved'], true)) {
                throw ValidationException::withMessages(['room' => 'Suite is not ready for check-in.']);
            }
            if (Booking::where('room_id', $room->id)
                ->whereKeyNot($stay->id)->whereNotNull('checked_in_at')
                ->whereNull('checked_out_at')->exists()) {
                throw ValidationException::withMessages(['room' => 'Another guest is currently checked into this suite.']);
            }

            $stay->forceFill(['checked_in_at' => now()])->save();
            $room->update(['status' => 'occupied']);
        }, 3);
    }

    public function checkOut(Booking $booking, User $staff): void
    {
        $this->authorize($staff);

        DB::transaction(function () use ($booking) {
            $room = Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            $stay = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (!$stay->checked_in_at || $stay->checked_out_at
                || !in_array($stay->status, ['confirmed', 'paid'], true)) {
                throw ValidationException::withMessages(['booking' => 'Only active checked-in stays can check out.']);
            }
            if ($room->status !== 'occupied') {
                throw ValidationException::withMessages(['room' => 'Room occupancy state must be reviewed before check-out.']);
            }

            $stay->forceFill(['checked_out_at' => now(), 'status' => 'completed'])->save();
            $room->update(['status' => 'cleaning']);
        }, 3);
    }
}
