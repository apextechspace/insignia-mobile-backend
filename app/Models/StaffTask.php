<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffTask extends Model
{
    protected $fillable = [
        'user_id',
        'room_id',
        'property_area_id',
        'verified_by',
        'verified_at',
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
        'verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (StaffTask $task): void {
            if ($task->property_area_id && $task->room_id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['property_area_id' => 'Choose either a guest room or a property area, not both.']);
            }
            if ($task->task_type === 'room_turnaround' && $task->property_area_id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['task_type' => 'Room turnaround is for guest rooms. Use Daily Cleaning / Refresh for a property area.']);
            }
            if ($task->property_area_id && (! $task->exists || $task->isDirty('property_area_id'))
                && ! PropertyArea::whereKey($task->property_area_id)->where('is_active', true)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['property_area_id' => 'Choose an active property area.']);
            }
            $requiresChecklist = $task->task_type === 'room_turnaround' || $task->property_area_id;
            if (! $requiresChecklist || ! in_array($task->status, ['completed', 'verified'], true)) {
                return;
            }
            $items = $task->checklist_items;
            if (! is_array($items) || count($items) === 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'checklist_items' => 'A completed cleaning checklist is required.',
                ]);
            }
            foreach ($items as $item) {
                if (empty($item['item']) || ($item['done'] ?? false) !== true) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'checklist_items' => 'Finish every cleaning checklist item.',
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

    public function propertyArea()
    {
        return $this->belongsTo(PropertyArea::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getLocationNameAttribute(): string
    {
        return $this->propertyArea?->name ?? $this->room?->name ?? 'General task';
    }

    public static function areaCleaningChecklist(): array
    {
        return [
            ['item' => 'Sweep and remove dirt or litter', 'done' => false],
            ['item' => 'Clean appropriate floors and surfaces', 'done' => false],
            ['item' => 'Empty bins and tidy the area', 'done' => false],
            ['item' => 'Check the area is clean and ready', 'done' => false],
        ];
    }
}
