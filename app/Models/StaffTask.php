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

    protected static function booted(): void
    {
        static::saving(function (StaffTask $task): void {
            if ($task->task_type !== 'room_turnaround' || $task->status !== 'completed') {
                return;
            }
            $items = $task->checklist_items;
            if (!is_array($items) || count($items) === 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'checklist_items' => 'Checkout checklist is required.',
                ]);
            }
            foreach ($items as $item) {
                if (empty($item['item']) || ($item['done'] ?? false) !== true) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'checklist_items' => 'Finish every checkout checklist item.',
                    ]);
                }
            }
            $task->completed_at ??= now();
        });
    }

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
