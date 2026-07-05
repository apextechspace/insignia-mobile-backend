<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FoodOrder;
use App\Models\FoodOrderItem;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FoodOrderController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:room_service,pickup',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'metadata' => 'nullable|array',
            'payment_reference' => 'nullable|string|max:255',
            'receipt' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $receiptUrl = null;
            if ($request->hasFile('receipt')) {
                $receiptUrl = $request->file('receipt')->store('receipts', 'public');
            }

            $totalAmount = 0;
            $orderItems = [];

            // Calculate exact total from DB to prevent frontend tampering
            foreach ($request->items as $item) {
                $menuItem = MenuItem::findOrFail($item['menu_item_id']);
                
                if (!$menuItem->is_available) {
                    throw new \Exception("Item '{$menuItem->name}' is currently unavailable.");
                }

                $itemTotal = $menuItem->price * $item['quantity'];
                $totalAmount += $itemTotal;

                $orderItems[] = [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $item['quantity'],
                    'price' => $menuItem->price, // Store the price at the time of order
                ];
            }

            $order = FoodOrder::create([
                'user_id' => $request->user()->id,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'type' => $request->type,
                'payment_reference' => $request->payment_reference,
                'receipt_url' => $receiptUrl,
                'metadata' => $request->metadata ?? [],
            ]);

            foreach ($orderItems as $orderItem) {
                $orderItem['food_order_id'] = $order->id;
                FoodOrderItem::create($orderItem);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order placed successfully',
                'data' => $order->load('items.menuItem')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to place order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $order = FoodOrder::with('items.menuItem')->findOrFail($id);

        // Ensure user can only view their own orders
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => $order
        ]);
    }
}
