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
        return array_map(function ($image) {
            if (strpos($image, 'http') === 0) return $image;
            return asset('storage/' . $image);
        }, $images);
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
}
