<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestCityLedgerEntry extends Model
{
    protected $fillable = ['guest_city_ledger_account_id', 'booking_id', 'type', 'amount_kobo', 'reference', 'payment_method', 'evidence_path', 'notes', 'entered_by', 'posted_at'];
    protected $casts = ['posted_at' => 'datetime'];

    public function account() { return $this->belongsTo(GuestCityLedgerAccount::class, 'guest_city_ledger_account_id'); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function enteredBy() { return $this->belongsTo(User::class, 'entered_by'); }
}
