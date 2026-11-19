<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Booking $stay): void {
            if ($stay->check_in && $stay->check_out && $stay->check_out > $stay->check_in) {
                $stay->reservation_terms = \App\Services\ReservationTerms::snapshot($stay->check_in, $stay->check_out, (string) $stay->total_price,
                    $stay->reserved_by !== null || $stay->minimum_payment_kobo !== null, $stay->minimum_payment_kobo);
                if ($stay->reserved_by !== null && $stay->minimum_payment_kobo === null) {
                    $stay->minimum_payment_kobo = $stay->reservation_terms['minimum_kobo'];
                }
            }
        });
        static::updating(function (Booking $stay): void {
            if ($stay->getOriginal('reservation_terms') !== null && $stay->isDirty(['reservation_terms', 'room_id', 'check_in', 'check_out', 'total_price'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['reservation_terms' => 'Agreed reservation terms cannot be overwritten.']);
            }
        });
    }

    public function scopeActionablePending(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'pending')->where(fn ($q) => $q->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()));
    }

    use \App\Models\Concerns\ProtectsPaidDetails;

    protected array $paidDetailFields = ['user_id', 'room_id', 'check_in', 'check_out', 'total_price'];

    protected $casts = [
        'reservation_terms' => 'array',
        'total_price' => 'decimal:2',
        'hold_expires_at' => 'datetime',
        'booking_contact' => 'array',
        'guest_registration' => 'array',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    protected $hidden = ['guest_registration', 'booking_contact', 'reserved_by', 'deposit_exception_by', 'deposit_exception_reason', 'deposit_exception_at'];

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
        'reserved_by',
        'minimum_payment_kobo',
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
        return $value ? asset('storage/'.$value) : null;
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
