<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetHandover extends Model
{
    protected $fillable = [
        'room_id',
        'handover_date',
        'status',
        'condition_notes',
        'signed_document_pdf',
        'mcl_representative_id',
        'mcl_signed_at',
        'ipl_representative_id',
        'ipl_signed_at',
    ];

    protected $casts = [
        'handover_date' => 'date',
        'mcl_signed_at' => 'datetime',
        'ipl_signed_at' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function mclRepresentative()
    {
        return $this->belongsTo(User::class, 'mcl_representative_id');
    }

    public function iplRepresentative()
    {
        return $this->belongsTo(User::class, 'ipl_representative_id');
    }
}
