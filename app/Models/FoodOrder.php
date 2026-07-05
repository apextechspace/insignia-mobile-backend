<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FoodOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total_amount',
        'status', // pending, paid, preparing, delivered, cancelled
        'type',   // room_service, pickup
        'payment_reference',
        'receipt_url',
        'metadata',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'metadata' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getReceiptUrlAttribute($value)
    {
        return $value ? asset('storage/' . $value) : null;
    }

    public function items()
    {
        return $this->hasMany(FoodOrderItem::class);
    }
}
