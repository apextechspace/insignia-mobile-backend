<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestRegistration extends Model
{
    protected $fillable = [
        'user_id', 'guest_name', 'guest_phone', 'relationship',
        'visit_date', 'expected_arrival', 'purpose', 'status', 'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
