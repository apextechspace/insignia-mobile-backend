<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Booking;
use App\Models\Room;
use App\Http\Requests\StoreBookingRequest;

class BookingController extends Controller
{
    public function cancelCheckout(Request $request, Booking $booking)
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 404);
        \Illuminate\Support\Facades\DB::transaction(function () use ($booking) {
            Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            $record = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless(!$record->paid_at && $record->status === 'pending' && $record->hold_expires_at, 409);
            $record->update(['status' => 'cancelled']);
        }, 3);
        return response()->json(['message' => 'Checkout released. Any late payment will require finance review.']);
    }

    public function index(Request $request)
    {
        return response()->json($request->user()->bookings()->with('room')->get());
    }

    public function store(StoreBookingRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('receipt') && !\App\Models\ManualBankAccount::isReady()) {
            return response()->json(['message' => 'Manual bank transfer is currently unavailable. Please use Flutterwave.'], 422);
        }

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

            $overlap = \App\Services\RoomAvailability::blocking($validated['check_in'], $validated['check_out'])
                ->where('room_id', $room->id)->exists();

            if ($overlap) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'room_id' => 'This suite is booked or temporarily held for those dates. Please choose another suite or try again later.',
                ]);
            }

            $nights = (new \DateTimeImmutable($validated['check_in']))
                ->diff(new \DateTimeImmutable($validated['check_out']))->days;
            $totalPrice = $nights * $room->price_per_night;

            $receiptUrl = $request->hasFile('receipt')
                ? $request->file('receipt')->store('receipts', 'public') : null;

            return $request->user()->bookings()->create([
                'room_id' => $room->id,
                'check_in' => $validated['check_in'],
                'check_out' => $validated['check_out'],
                'total_price' => $totalPrice,
                'status' => 'pending',
                'hold_expires_at' => $receiptUrl ? null : now()->addMinutes(15),
                'guests' => $validated['guests'] ?? 1,
                'special_requests' => $validated['special_requests'] ?? null,
                'booking_contact' => ['name' => $validated['guest_name'] ?? $request->user()->name,
                    'phone' => $validated['guest_phone'] ?? $request->user()->phone, 'email' => $request->user()->email],
                'payment_reference' => $validated['payment_reference'] ?? null,
                'receipt_url' => $receiptUrl,
                'payment_method' => $receiptUrl ? 'bank_transfer' : 'flutterwave',
            ]);
        }, 3);

        return response()->json($booking->load('room'), 201);
    }
}
