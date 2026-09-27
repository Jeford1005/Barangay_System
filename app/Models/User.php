<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_STAFF = 'staff';

    public const ROLE_RESIDENT = 'resident';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'status',
        'approved_at',
        'reviewed_by',
        'rejection_reason',
        'suspended_at',
        'suspended_by',
        'suspension_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'approved_at' => 'datetime',
            'reviewed_by' => 'integer',
            'suspended_at' => 'datetime',
            'suspended_by' => 'integer',
        ];
    }

    public function residentProfile()
    {
        return $this->belongsTo(Resident::class, 'id', 'user_id');
    }

    public function residentApplication()
    {
        return $this->hasOne(ResidentApplication::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function suspender()
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function isActive(): bool
    {
        return $this->isApproved() && ! $this->isSuspended();
    }

    public function isAdmin(): bool
    {
        return $this->user_type === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return $this->user_type === self::ROLE_STAFF;
    }

    public function isOfficeUser(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    public function roleLabel(): string
    {
        return match ($this->user_type) {
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_STAFF => 'Staff',
            self::ROLE_RESIDENT => 'Resident',
            default => ucfirst((string) $this->user_type),
        };
    }

    /**
     * Central permission map for the initial fixed roles.
     *
     * Administrators intentionally receive every permission. Staff receive
     * only day-to-day operational permissions; administrator controls are
     * not listed here.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->isStaff() && in_array($permission, [
            'operations.access',
            'dashboard.view',
            'residents.view',
            'residents.manage',
            'households.view',
            'households.manage',
            'puroks.view',
            'blotter.view',
            'blotter.manage',
            'welfare.view',
            'welfare.intake',
            'certificates.view',
            'certificates.issue',
            'certificate-requests.view',
            'resident-changes.view',
            'reports.view',
            'analytics.view',
        ], true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'approved')->whereNull('suspended_at');
    }
}
