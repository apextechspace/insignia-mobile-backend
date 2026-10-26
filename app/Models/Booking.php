<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'room_id',
        'check_in',
        'check_out',
        'total_price',
        'status',
        'payment_reference',
        'receipt_url',
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

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function accommodationLedger()
    {
        return $this->hasOne(AccommodationLedger::class);
    }

    public function getCheckInDateAttribute()
    {
        return $this->check_in;
    }

    public function getCheckOutDateAttribute()
    {
        return $this->check_out;
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }
}
