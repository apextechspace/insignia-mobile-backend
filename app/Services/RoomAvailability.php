<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class RoomAvailability
{
    // Call inside the transaction that writes the booking.
    public static function lockAvailableRoom(int $roomId, string $checkIn, string $checkOut): Room
    {
        $room = Room::whereKey($roomId)->lockForUpdate()->firstOrFail();
        if (! $room->is_available || $room->status === 'maintenance') {
            throw ValidationException::withMessages(['room_id' => 'This suite is unavailable for reservations.']);
        }
        if (self::blocking($checkIn, $checkOut)->where('room_id', $room->id)->exists()) {
            throw ValidationException::withMessages([
                'room_id' => 'This suite is booked or temporarily held for those dates. Please choose another suite or try again later.',
            ]);
        }

        return $room;
    }

    public static function quote(Room $room, string $checkIn, string $checkOut): string
    {
        $nights = (new \DateTimeImmutable($checkIn))->diff(new \DateTimeImmutable($checkOut))->days;

        return number_format($nights * (int) round((float) $room->price_per_night * 100) / 100, 2, '.', '');
    }

    public static function blocking(string $checkIn, string $checkOut, ?int $except = null): Builder
    {
        return Booking::query()->when($except, fn ($q) => $q->whereKeyNot($except))
            ->where('check_in', '<', $checkOut)->where('check_out', '>', $checkIn)
            ->where(fn ($q) => $q->whereIn('status', ['confirmed', 'paid', 'completed'])
                ->orWhere(fn ($q) => $q->where('status', 'pending')->where('hold_expires_at', '>', now())));
    }
}
