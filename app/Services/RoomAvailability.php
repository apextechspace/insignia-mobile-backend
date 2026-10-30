<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;

class RoomAvailability
{
    public static function blocking(string $checkIn, string $checkOut, ?int $except = null): Builder
    {
        return Booking::query()->when($except, fn ($q) => $q->whereKeyNot($except))
            ->where('check_in', '<', $checkOut)->where('check_out', '>', $checkIn)
            ->where(fn ($q) => $q->whereIn('status', ['confirmed', 'paid', 'completed'])
                ->orWhere(fn ($q) => $q->where('status', 'pending')->where('hold_expires_at', '>', now())));
    }
}
