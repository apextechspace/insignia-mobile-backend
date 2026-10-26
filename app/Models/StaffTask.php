<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffTask extends Model
{
    protected $fillable = [
        'user_id',
        'room_id',
        'task_title',
        'task_type',
        'priority',
        'status',
        'checklist_items',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'checklist_items' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
