<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Requests\StoreOrderRequest;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->orders()->with('product')->get());
    }

    public function store(StoreOrderRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('receipt') && !\App\Models\ManualBankAccount::isReady()) {
            return response()->json(['message' => 'Manual bank transfer is currently unavailable. Please use Flutterwave.'], 422);
        }

        $receiptUrl = null;
        if ($request->hasFile('receipt')) {
            $path = $request->file('receipt')->store('receipts', 'public');
            $receiptUrl = $path;
        }

        $product = Product::findOrFail($validated['product_id']);

        // 🚨 CRITICAL: Check stock availability
        if ($product->stock < $validated['quantity']) {
            return response()->json(['message' => 'Insufficient stock. Only ' . $product->stock . ' items remaining.'], 422);
        }

        $totalPrice = ($product->price * $validated['quantity']) + 5000; // Adding delivery fee

        // 🚨 CRITICAL: Reduce stock
        $product->decrement('stock', $validated['quantity']);

        $order = $request->user()->orders()->create([
            'product_id' => $validated['product_id'],
            'quantity' => $validated['quantity'],
            'total_price' => $totalPrice,
            'notes' => $validated['notes'],
            'status' => 'pending',
            'payment_reference' => $validated['payment_reference'],
            'receipt_url' => $receiptUrl,
            'delivery_location' => $validated['delivery_location'] ?? null,
        ]);

        return response()->json($order->load('product'), 201);
    }
}
