<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffShift extends Model
{
    protected $fillable = [
        'user_id',
        'shift_date',
        'shift_type',
        'start_time',
        'end_time',
        'status',
        'handover_notes',
    ];

    protected $casts = [
        'shift_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
