<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceBooking extends Model
{
    protected $fillable = [
        'user_id',
        'service_id',
        'service_variant_id',
        'scheduled_at',
        'date',
        'time',
        'notes',
        'total_price',
        'status',
        'payment_reference',
        'receipt_url',
        'metadata',
        'payment_method',
        'opay_reference',
        'opay_order_id',
        'flutterwave_reference',
        'flutterwave_transaction_id',
        'paid_at',
    ];

    protected $casts = [
        'metadata' => 'json',
        'scheduled_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::updated(function ($booking) {
            if ($booking->isDirty('status')) {
                try {
                    $booking->user->notify(new \App\Notifications\BookingStatusUpdated($booking, $booking->status));
                } catch (\Exception $e) {
                    \Log::error("Failed to notify user for booking {$booking->id}: " . $e->getMessage());
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getReceiptUrlAttribute($value)
    {
        return $value ? asset('storage/' . $value) : null;
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function variant()
    {
        return $this->belongsTo(ServiceVariant::class, 'service_variant_id');
    }
}
