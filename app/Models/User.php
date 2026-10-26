<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_IPL_ADMIN = 'ipl_admin';
    public const ROLE_DELIVERY_PARTNER = 'delivery_partner';
    public const ROLE_GUEST_RELATIONS = 'guest_relations';
    public const ROLE_STAFF = 'staff';
    public const ROLE_USER = 'user';

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            // Door 1 (Owner's Portal): Only IPL Master Admins
            return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_IPL_ADMIN]);
        }

        if ($panel->getId() === 'operations') {
            // Door 2 (Facility Portal): Delivery Partner (MCL), Front Desk, Staff, or Super Admin
            return in_array($this->role, [
                self::ROLE_ADMIN,
                self::ROLE_IPL_ADMIN,
                self::ROLE_DELIVERY_PARTNER,
                self::ROLE_GUEST_RELATIONS,
                self::ROLE_STAFF,
            ]);
        }

        return false;
    }

    public function isIplAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_IPL_ADMIN]);
    }

    public function isDeliveryPartner(): bool
    {
        return $this->role === self::ROLE_DELIVERY_PARTNER;
    }

    public function isGuestRelations(): bool
    {
        return $this->role === self::ROLE_GUEST_RELATIONS;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar_url',
        'phone',
        'security_pin',
        'expo_push_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'security_pin',
    ];

    protected $appends = ['has_security_pin'];

    public function getHasSecurityPinAttribute(): bool
    {
        return !is_null($this->security_pin);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function serviceBookings()
    {
        return $this->hasMany(ServiceBooking::class);
    }

    public function guestRegistrations()
    {
        return $this->hasMany(GuestRegistration::class);
    }

    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'logged_by');
    }

    public function pettyCashTransactions()
    {
        return $this->hasMany(PettyCashTransaction::class, 'handled_by');
    }

    public function dailyReconciliations()
    {
        return $this->hasMany(DailyReconciliation::class, 'submitted_by');
    }

    public function monthlyStatements()
    {
        return $this->hasMany(MonthlyStatement::class, 'submitted_by');
    }

    public function staffShifts()
    {
        return $this->hasMany(StaffShift::class);
    }

    public function staffTasks()
    {
        return $this->hasMany(StaffTask::class);
    }

    public function reportedIncidents()
    {
        return $this->hasMany(Incident::class, 'reported_by');
    }

    public function assignedIncidents()
    {
        return $this->hasMany(Incident::class, 'assigned_to');
    }
}
