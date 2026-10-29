<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceBooking;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Http\Requests\StoreServiceBookingRequest;
use App\Actions\CreateServiceBookingAction;

class ServiceBookingController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->serviceBookings()->with(['service', 'variant'])->get());
    }

    public function store(StoreServiceBookingRequest $request, CreateServiceBookingAction $createServiceBookingAction)
    {
        $validated = $request->validated();

        if ($request->hasFile('receipt') || ($validated['payment_method'] ?? null) === 'bank_transfer') {
            return response()->json(['message' => 'Service payments use Flutterwave. Direct bank transfer is only available for room reservations.'], 422);
        }

        $receiptUrl = null;
        if ($request->hasFile('receipt')) {
            $path = $request->file('receipt')->store('receipts', 'public');
            $receiptUrl = $path;
        }

        $paymentMethod = $validated['payment_method'] ?? ($receiptUrl ? 'bank_transfer' : 'flutterwave');

        $serviceBooking = $createServiceBookingAction->execute(array_merge($validated, [
            'user_id' => $request->user()->id,
            'receipt_url' => $receiptUrl,
            'payment_method' => $paymentMethod,
        ]));

        return response()->json($serviceBooking->load(['service', 'variant']), 201);
    }
}
