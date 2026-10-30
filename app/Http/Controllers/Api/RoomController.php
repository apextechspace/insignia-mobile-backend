<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $dates = $request->validate([
            'check_in' => 'required_with:check_out|date_format:Y-m-d|after_or_equal:today',
            'check_out' => 'required_with:check_in|date_format:Y-m-d|after:check_in',
            'guests' => 'nullable|integer|min:1|max:4',
        ]);
        $rooms = Room::where('is_available', true)->where('status', '!=', 'maintenance');
        if (!empty($dates['check_in'])) {
            $rooms->whereNotIn('id', \App\Services\RoomAvailability::blocking($dates['check_in'], $dates['check_out'])->select('room_id'));
        }
        return response()->json($rooms->get()->map(function ($room) use ($dates) {
            if (!empty($dates['check_in'])) {
                $nights = (new \DateTimeImmutable($dates['check_in']))->diff(new \DateTimeImmutable($dates['check_out']))->days;
                $room->setAttribute('stay_total', round($nights * $room->price_per_night, 2));
                $room->setAttribute('nights', $nights);
            }
            return $room;
        }));
    }

    public function show(Room $room)
    {
        abort_unless($room->is_available && $room->status !== 'maintenance', 404);
        return response()->json($room);
    }
}
