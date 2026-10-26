<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    public const APPROVAL_THRESHOLD = 50000.00; // Naira threshold where partner expense requires IPL approval

    protected $fillable = [
        'title',
        'category',
        'amount',
        'currency',
        'payment_method',
        'receipt_photo',
        'room_id',
        'logged_by',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'expense_date',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'expense_date' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function logger()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function pettyCashTransactions()
    {
        return $this->hasMany(PettyCashTransaction::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
