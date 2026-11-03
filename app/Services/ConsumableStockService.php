<?php

namespace App\Services;

use App\Models\ConsumableItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConsumableStockService
{
    public function issueToRoom(ConsumableItem $item, User $actor, array $data): void
    {
        $data = Validator::make($data, ['room_id' => 'required|exists:rooms,id', 'quantity' => 'required|integer|min:1', 'notes' => 'nullable|string|max:2000'])->validate();
        DB::transaction(function () use ($item, $actor, $data) {
            $locked = ConsumableItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($locked->current_stock < $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => 'There is not enough stock to issue that quantity.']);
            }
            StockMovement::create(['consumable_item_id' => $locked->id, 'movement_type' => 'room_issuance',
                'quantity' => -$data['quantity'], 'room_id' => $data['room_id'], 'handled_by' => $actor->id, 'notes' => $data['notes'] ?? null]);
            $locked->decrement('current_stock', $data['quantity']);
        }, 3);
    }

    public function restock(ConsumableItem $item, User $actor, array $data): void
    {
        $data = Validator::make($data, ['quantity' => 'required|integer|min:1', 'notes' => 'nullable|string|max:2000'])->validate();
        DB::transaction(function () use ($item, $actor, $data) {
            $locked = ConsumableItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            StockMovement::create(['consumable_item_id' => $locked->id, 'movement_type' => 'purchase_restock',
                'quantity' => $data['quantity'], 'handled_by' => $actor->id, 'notes' => $data['notes'] ?? null]);
            $locked->increment('current_stock', $data['quantity']);
        }, 3);
    }
}
