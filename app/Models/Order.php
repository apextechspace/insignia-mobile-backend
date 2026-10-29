<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use \App\Models\Concerns\ProtectsPaidDetails;

    protected array $paidDetailFields = ['user_id', 'product_id', 'quantity', 'total_price'];

    protected $casts = ['stock_deducted' => 'boolean', 'total_price' => 'decimal:2'];

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
        'payment_method',
        'opay_reference',
        'opay_order_id',
        'flutterwave_reference',
        'flutterwave_transaction_id',
        'paid_at',
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
