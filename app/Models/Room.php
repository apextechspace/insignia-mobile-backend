<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $hidden = ['housekeeping_notes'];

    protected static function booted(): void
    {
        static::updating(function (Room $room): void {
            if (!$room->isDirty('status') || $room->status !== 'available'
                || $room->getOriginal('status') !== 'cleaning') {
                return;
            }

            // Fail closed even if an old Filament action attempts to bypass
            // supervisor release of a checkout-cleaning task.
            $verified = \App\Models\StaffTask::where('room_id', $room->id)
                ->where('task_type', 'room_turnaround')
                ->latest('id')->first();

            if (!$verified || $verified->status !== 'verified') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'room' => 'A supervisor must verify the latest turnaround checklist before this room can be marked ready.',
                ]);
            }

            if (\App\Models\Booking::where('room_id', $room->id)
                ->whereNotNull('checked_in_at')->whereNull('checked_out_at')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'room' => 'An active checked-in stay prevents releasing this room.',
                ]);
            }
        });
    }

    protected $fillable = [
        'name',
        'type',
        'description',
        'price_per_night',
        'tag',
        'category_description',
        'amenities',
        'images',
        'video_url',
        'is_available',
        'status',           // Operations: available | occupied | cleaning | maintenance | reserved
        'housekeeping_notes',
    ];

    protected $casts = [
        'amenities' => 'array',
        'images' => 'array',
        'is_available' => 'boolean',
    ];

    protected $appends = ['image'];

    public function getImagesAttribute($value)
    {
        $images = !is_null($value) && !is_array($value) ? json_decode($value, true) : $value;
        $images = $images ?: [];

        $cleanRelative = function ($image) {
            if (is_string($image)) {
                if (str_contains($image, '/storage/')) {
                    return explode('/storage/', $image)[1];
                }
                if (str_starts_with($image, 'storage/')) {
                    return substr($image, 8);
                }
            }
            return $image;
        };

        // If inside Filament admin/operations panel, Livewire, or console (tinker/commands/seeders), keep relative paths
        if (
            (app()->runningInConsole() && !app()->runningUnitTests()) ||
            (function_exists('filament') && filament()->isServing()) ||
            (request() && request()->is('admin*', 'operations*', 'livewire*'))
        ) {
            return array_values(array_filter(array_map($cleanRelative, $images)));
        }

        return array_values(array_filter(array_map(function ($image) use ($cleanRelative) {
            $image = $cleanRelative($image);
            if (is_string($image) && str_starts_with($image, 'http')) return $image;
            return asset('storage/' . $image);
        }, $images)));
    }

    public function setImagesAttribute($value): void
    {
        $images = !is_null($value) && !is_array($value) ? json_decode($value, true) : $value;
        $images = $images ?: [];

        $cleaned = array_values(array_filter(array_map(function ($image) {
            if (!is_string($image)) return $image;
            if (str_contains($image, '/storage/')) {
                return explode('/storage/', $image)[1];
            }
            if (str_starts_with($image, 'storage/')) {
                return substr($image, 8);
            }
            return $image;
        }, $images)));

        $this->attributes['images'] = json_encode($cleaned);
    }

    public function getVideoUrlAttribute($value)
    {
        if (!$value) return null;

        $cleanRelative = function ($video) {
            if (is_string($video)) {
                if (str_contains($video, '/storage/')) {
                    return explode('/storage/', $video)[1];
                }
                if (str_starts_with($video, 'storage/')) {
                    return substr($video, 8);
                }
            }
            return $video;
        };

        if (
            (app()->runningInConsole() && !app()->runningUnitTests()) ||
            (function_exists('filament') && filament()->isServing()) ||
            (request() && request()->is('admin*', 'operations*', 'livewire*'))
        ) {
            return $cleanRelative($value);
        }

        $value = $cleanRelative($value);
        if (str_starts_with($value, 'http')) return $value;
        return asset('storage/' . $value);
    }

    public function setVideoUrlAttribute($value): void
    {
        if (!$value) {
            $this->attributes['video_url'] = null;
            return;
        }

        if (is_string($value)) {
            if (str_contains($value, '/storage/')) {
                $value = explode('/storage/', $value)[1];
            } elseif (str_starts_with($value, 'storage/')) {
                $value = substr($value, 8);
            }
        }

        $this->attributes['video_url'] = $value;
    }

    public function getImageUrlAttribute()
    {
        $images = $this->images;
        return count($images) > 0 ? $images[0] : 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=1000&q=80';
    }

    public function getImageAttribute()
    {
        return $this->image_url;
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The single active booking for this room (for dashboard guest display).
     */
    public function activeBooking()
    {
        return $this->hasOne(Booking::class)
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->whereIn('status', ['confirmed', 'paid', 'active'])
            ->latest('checked_in_at');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function accommodationLedgers()
    {
        return $this->hasMany(AccommodationLedger::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }

    public function staffTasks()
    {
        return $this->hasMany(StaffTask::class);
    }
}
