<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use \App\Models\Concerns\ProtectsPaidDetails;

    protected array $paidDetailFields = ['user_id', 'room_id', 'check_in', 'check_out', 'total_price'];

    protected $casts = [
        'total_price' => 'decimal:2',
        'hold_expires_at' => 'datetime',
        'booking_contact' => 'array',
        'guest_registration' => 'array',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    protected $hidden = ['guest_registration', 'booking_contact'];

    public function checkedInBy()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy()
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    protected $fillable = [
        'user_id',
        'hold_expires_at',
        'guests',
        'special_requests',
        'booking_contact',
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
