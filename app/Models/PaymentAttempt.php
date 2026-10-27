<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'gateway', 'reference', 'payable_type', 'payable_id',
        'amount', 'currency', 'status', 'provider_transaction_id',
        'confirmed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    public function payable()
    {
        return $this->morphTo();
    }
}
