<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceVariant extends Model
{
    protected $fillable = [
        'service_id',
        'name',
        'price',
        'duration_minutes',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'metadata' => 'json',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
