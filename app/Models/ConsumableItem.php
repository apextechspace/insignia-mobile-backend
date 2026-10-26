<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumableItem extends Model
{
    protected $fillable = [
        'name',
        'category',
        'unit',
        'current_stock',
        'minimum_alert_level',
        'unit_cost',
    ];

    protected $casts = [
        'current_stock' => 'integer',
        'minimum_alert_level' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->current_stock <= $this->minimum_alert_level;
    }
}
