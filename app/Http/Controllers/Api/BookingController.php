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

        // Serialize booking creation on the suite row. Confirmations and manual
        // approvals use the same room lock, so overlapping paid stays cannot
        // be missed between the availability check and reservation creation.
        $booking = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $validated) {
            $room = Room::whereKey($validated['room_id'])->lockForUpdate()->firstOrFail();

            if (!$room->is_available || $room->status === 'maintenance') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'room_id' => 'This suite is unavailable for reservations.',
                ]);
            }

            $overlap = Booking::where('room_id', $room->id)
                ->whereIn('status', ['confirmed', 'paid', 'completed'])
                ->where('check_in', '<', $validated['check_out'])
                ->where('check_out', '>', $validated['check_in'])
                ->exists();

            if ($overlap) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'room_id' => 'This suite is already confirmed for another guest on those dates.',
                ]);
            }

            $nights = (new \DateTimeImmutable($validated['check_in']))
                ->diff(new \DateTimeImmutable($validated['check_out']))->days;
            $totalPrice = $nights * $room->price_per_night + 25000;

            $receiptUrl = $request->hasFile('receipt')
                ? $request->file('receipt')->store('receipts', 'public') : null;

            return $request->user()->bookings()->create([
                'room_id' => $room->id,
                'check_in' => $validated['check_in'],
                'check_out' => $validated['check_out'],
                'total_price' => $totalPrice,
                'status' => 'pending',
                'payment_reference' => $validated['payment_reference'] ?? null,
                'receipt_url' => $receiptUrl,
                'payment_method' => $receiptUrl ? 'bank_transfer' : null,
            ]);
        }, 3);

        return response()->json($booking->load('room'), 201);
    }
}
