<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'consumable_item_id',
        'movement_type',
        'quantity',
        'room_id',
        'expense_id',
        'handled_by',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function consumableItem()
    {
        return $this->belongsTo(ConsumableItem::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
