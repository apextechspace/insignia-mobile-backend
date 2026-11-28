<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestCityLedgerAccount extends Model
{
    protected $fillable = ['user_id', 'account_reference', 'guest_name', 'phone', 'email', 'status', 'notes', 'opened_by'];

    public function user() { return $this->belongsTo(User::class); }
    public function openedBy() { return $this->belongsTo(User::class, 'opened_by'); }
    public function entries() { return $this->hasMany(GuestCityLedgerEntry::class); }
}
