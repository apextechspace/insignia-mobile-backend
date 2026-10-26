<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReconciliationCase extends Model
{
    protected $fillable = [
        'gateway',
        'payment_reference',
        'provider_transaction_id',
        'payable_type',
        'payable_id',
        'reason',
        'status',
        'resolution_notes',
        'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];
}
