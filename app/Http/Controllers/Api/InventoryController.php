<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumableItem;
use App\Models\User;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(in_array($request->user()->role, [
            User::ROLE_ADMIN, User::ROLE_IPL_ADMIN, User::ROLE_DELIVERY_PARTNER,
            User::ROLE_GUEST_RELATIONS, User::ROLE_STAFF,
        ], true), 403);

        return response()->json(ConsumableItem::orderBy('category')->orderBy('name')->get()->map(
            fn (ConsumableItem $item) => [
                'id' => $item->id, 'name' => $item->name, 'category' => $item->category,
                'unit' => $item->unit, 'current_stock' => $item->current_stock,
                'minimum_alert_level' => $item->minimum_alert_level, 'is_low_stock' => $item->isLowStock(),
            ]
        ));
    }
}
