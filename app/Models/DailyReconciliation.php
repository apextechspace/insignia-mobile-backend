<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReconciliation extends Model
{
    protected $fillable = [
        'reconciliation_date',
        'shift',
        'recorded_accommodation',
        'recorded_ancillary_cash',
        'actual_cash_counted',
        'actual_pos_total',
        'actual_transfer_total',
        'discrepancy',
        'notes',
        'pos_settlement_receipt',
        'submitted_by',
        'status',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'recorded_accommodation' => 'decimal:2',
        'recorded_ancillary_cash' => 'decimal:2',
        'actual_cash_counted' => 'decimal:2',
        'actual_pos_total' => 'decimal:2',
        'actual_transfer_total' => 'decimal:2',
        'discrepancy' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
