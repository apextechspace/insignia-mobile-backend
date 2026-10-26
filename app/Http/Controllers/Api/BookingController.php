<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Booking;
use App\Models\Room;
use App\Http\Requests\StoreBookingRequest;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->bookings()->with('room')->get());
    }

    public function store(StoreBookingRequest $request)
    {
        $validated = $request->validated();

        $receiptUrl = null;
        if ($request->hasFile('receipt')) {
            $path = $request->file('receipt')->store('receipts', 'public');
            $receiptUrl = $path;
        }

        // Never delete prior pending bookings: they may contain manual receipts
        // or Flutterwave attempts which arrive after a retry.
        // 🚨 CRITICAL: Check for overlapping confirmed stays
        $overlap = Booking::where('room_id', $validated['room_id'])
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('check_in', '<', $validated['check_out'])
            ->where('check_out', '>', $validated['check_in'])
            ->exists();

        if ($overlap) {
            return response()->json(['message' => 'This room is already confirmed for another guest on the selected dates.'], 422);
        }

        $room = Room::findOrFail($validated['room_id']);
        
        $checkIn = new \DateTime($validated['check_in']);
        $checkOut = new \DateTime($validated['check_out']);
        $nights = $checkIn->diff($checkOut)->days;
        $nights = $nights > 0 ? $nights : 1;
        
        $totalPrice = ($nights * $room->price_per_night) + 25000; // Adding executive service fee

        $booking = $request->user()->bookings()->create([
            'room_id' => $validated['room_id'],
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'total_price' => $totalPrice,
            'status' => 'pending', 
            'payment_reference' => $validated['payment_reference'] ?? null,
            'receipt_url' => $receiptUrl,
        ]);

        return response()->json($booking->load('room'), 201);
    }
}
