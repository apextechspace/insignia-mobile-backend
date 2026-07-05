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
}
