<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
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

        // If inside Filament admin/operations panel request, keep relative paths for FileUpload component
        if (request() && (request()->is('admin*') || request()->is('operations*') || request()->is('livewire*'))) {
            return array_map(function ($image) {
                if (is_string($image) && str_contains($image, '/storage/')) {
                    return explode('/storage/', $image)[1];
                }
                return $image;
            }, $images);
        }

        return array_map(function ($image) {
            if (is_string($image) && strpos($image, 'http') === 0) return $image;
            return asset('storage/' . $image);
        }, $images);
    }

    public function getVideoUrlAttribute($value)
    {
        if (!$value) return null;

        if (request() && (request()->is('admin*') || request()->is('operations*') || request()->is('livewire*'))) {
            if (str_contains($value, '/storage/')) {
                return explode('/storage/', $value)[1];
            }
            return $value;
        }

        if (strpos($value, 'http') === 0) return $value;
        return asset('storage/' . $value);
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
            ->whereIn('status', ['confirmed', 'paid', 'active'])
            ->whereDate('check_in', '<=', now())
            ->whereDate('check_out', '>=', now())
            ->latest();
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
