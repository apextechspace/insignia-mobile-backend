<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyStatement extends Model
{
    protected $fillable = [
        'statement_code',
        'year',
        'month',
        'total_accommodation_revenue',
        'total_permitted_expenses',
        'management_fee_percentage',
        'management_fee_amount',
        'net_payable_to_ipl',
        'document_pdf',
        'status',
        'ipl_query_notes',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'total_accommodation_revenue' => 'decimal:2',
        'total_permitted_expenses' => 'decimal:2',
        'management_fee_percentage' => 'decimal:2',
        'management_fee_amount' => 'decimal:2',
        'net_payable_to_ipl' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
