<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'category',
        'type', // booking, reservation, request
        'description',
        'price',
        'image',
        'is_active',
        'requires_variant',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_variant' => 'boolean',
    ];

    protected $appends = ['image_url'];

    public function variants()
    {
        return $this->hasMany(ServiceVariant::class);
    }

    public function getImageUrlAttribute()
    {
        if (!$this->image) {
            return 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=300&q=80';
        }

        if (strpos($this->image, 'http') === 0) {
            return $this->image;
        }

        return asset('storage/' . $this->image);
    }

    public function serviceBookings()
    {
        return $this->hasMany(ServiceBooking::class);
    }
}
