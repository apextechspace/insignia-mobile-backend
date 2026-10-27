<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index()
    {
        return response()->json(Room::where('is_available', true)->where('status', '!=', 'maintenance')->get());
    }

    public function show(Room $room)
    {
        return response()->json($room);
    }
}
