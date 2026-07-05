<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'total_price',
        'status',
        'notes',
        'payment_reference',
        'receipt_url',
        'delivery_location',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getReceiptUrlAttribute($value)
    {
        return $value ? asset('storage/' . $value) : null;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
