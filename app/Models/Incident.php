<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = [
        'incident_code',
        'title',
        'type',
        'room_id',
        'asset_id',
        'booking_id',
        'description',
        'evidence_photo_1',
        'evidence_photo_2',
        'severity',
        'associated_cost',
        'expense_id',
        'status',
        'is_escalated_to_ipl',
        'ipl_escalation_notes',
        'immediate_action_taken',
        'resolution_summary',
        'assigned_to',
        'reported_by',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'associated_cost' => 'decimal:2',
        'is_escalated_to_ipl' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Incident $incident) {
            if (empty($incident->incident_code)) {
                $incident->incident_code = 'INC-' . date('Y') . '-' . strtoupper(\Illuminate\Support\Str::random(5));
            }
        });
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
