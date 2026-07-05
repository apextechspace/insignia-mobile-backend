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

        $receiptUrl = null;
        if ($request->hasFile('receipt')) {
            $path = $request->file('receipt')->store('receipts', 'public');
            $receiptUrl = $path;
        }

        $serviceBooking = $createServiceBookingAction->execute(array_merge($validated, [
            'user_id' => $request->user()->id,
            'receipt_url' => $receiptUrl,
        ]));

        return response()->json($serviceBooking->load(['service', 'variant']), 201);
    }
}
