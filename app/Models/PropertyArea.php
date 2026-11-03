<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PropertyArea extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::deleting(function (PropertyArea $area): void {
            if ($area->tasks()->exists()) {
                throw ValidationException::withMessages(['area' => 'This area has task history. Mark it inactive instead of deleting it.']);
            }
        });
    }

    public function tasks()
    {
        return $this->hasMany(StaffTask::class);
    }
}
