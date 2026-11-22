<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Booking $stay): void {
            if ($stay->check_in && $stay->check_out && $stay->check_out > $stay->check_in) {
                $stay->reservation_terms = $stay->external_source ? [
                    'version' => 3, 'external' => true, 'nights' => max(1, (int) \Carbon\CarbonImmutable::parse($stay->check_in)->diffInDays(\Carbon\CarbonImmutable::parse($stay->check_out))),
                    'deposit_mode' => 'external', 'minimum_kobo' => \App\Services\AssistedReservationPayments::kobo((string) $stay->total_price),
                    'cancellation_enabled' => false, 'channel_terms' => $stay->external_details['channel_terms'],
                    'check_in_at' => \Carbon\CarbonImmutable::parse($stay->check_in.' '.config('property.check_in_time'), config('property.timezone'))->toIso8601String(),
                    'check_out_at' => \Carbon\CarbonImmutable::parse($stay->check_out.' '.config('property.check_out_time'), config('property.timezone'))->toIso8601String(),
                ] : \App\Services\ReservationTerms::snapshot($stay->check_in, $stay->check_out, (string) $stay->total_price,
                    $stay->reserved_by !== null || $stay->minimum_payment_kobo !== null, $stay->minimum_payment_kobo);
                if ($stay->reserved_by !== null && $stay->minimum_payment_kobo === null) {
                    $stay->minimum_payment_kobo = $stay->reservation_terms['minimum_kobo'];
                }
            }
        });
        static::updating(function (Booking $stay): void {
            if ($stay->getOriginal('reservation_terms') !== null && $stay->isDirty(['reservation_terms', 'room_id', 'check_in', 'check_out', 'total_price', 'external_source', 'external_reference', 'external_unit_reference'])) {
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
        'external_details' => 'array',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    protected $hidden = ['guest_registration', 'booking_contact', 'reserved_by', 'deposit_exception_by', 'deposit_exception_reason', 'deposit_exception_at', 'external_details'];

    protected $appends = ['external_payment_status', 'external_channel_name'];

    public function getExternalChannelNameAttribute(): ?string
    {
        return $this->external_source ? \App\Services\ExternalReservations::sourceLabel($this) : null;
    }

    public function getExternalPaymentStatusAttribute(): ?string
    {
        if (!$this->external_source) return null;
        if ($this->status === 'cancelled') return 'cancelled';
        if (\App\Services\ExternalReservations::channelPaymentVerified($this)) return 'channel_verified';
        return match ($this->external_details['payment_arrangement'] ?? null) {
            'pay_at_property' => 'pay_at_property', 'channel_collects' => 'channel_review', default => 'arrangement_review',
        };
    }

    public function checkedInBy()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy()
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    protected $fillable = [
        'external_source', 'external_reference', 'external_unit_reference', 'external_details',
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
