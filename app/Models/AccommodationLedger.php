<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccommodationLedger extends Model
{
    protected $fillable = [
        'booking_id',
        'room_id',
        'reference',
        'type',
        'gross_amount',
        'net_amount',
        'payment_method',
        'channel',
        'status',
        'collected_by',
        'transaction_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AccommodationLedger $ledger) {
            if (empty($ledger->reference)) {
                $ledger->reference = 'ACL-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(5));
            }
        });
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
