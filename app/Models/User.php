<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_RESIDENT = 'resident';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * Privilege columns (role, status, approved_at, reviewed_by, suspended_*)
     * are intentionally NOT mass assignable. They are written only through
     * forceFill() in code paths that already passed an authorization check.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Roles                                                               */
    /* ------------------------------------------------------------------ */

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isResident(): bool
    {
        return $this->role === self::ROLE_RESIDENT;
    }

    /** Admins and staff work in the barangay office; residents use the portal. */
    public function isOfficeUser(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    /* ------------------------------------------------------------------ */
    /* Status                                                              */
    /* ------------------------------------------------------------------ */

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeWithRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    /* ------------------------------------------------------------------ */
    /* Relations                                                           */
    /* ------------------------------------------------------------------ */

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** The pending self-registration submitted with this account. */
    public function residentApplication(): HasOne
    {
        return $this->hasOne(ResidentApplication::class);
    }

    /* ------------------------------------------------------------------ */
    /* Permissions — single source of truth for role capability            */
    /* ------------------------------------------------------------------ */

    /**
     * The exact staff capability list (office users get these 17, admins get
     * everything, residents get none — they only use the self-service portal).
     *
     * @var list<string>
     */
    private const STAFF_PERMISSIONS = [
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
    ];

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isStaff()) {
            return in_array($permission, self::STAFF_PERMISSIONS, true);
        }

        return false;
    }

    /* ------------------------------------------------------------------ */
    /* Resident profile link                                               */
    /* ------------------------------------------------------------------ */

    /**
     * The resident record this account represents.
     *
     * Falls back to an email match so a resident registered in the office
     * system automatically picks up their profile on first portal visit.
     */
    public function linkedResident(): ?Resident
    {
        if ($this->resident_id !== null) {
            return Resident::find($this->resident_id);
        }

        if (! $this->isResident()) {
            return null;
        }

        $resident = Resident::where('email', $this->email)->first();

        if ($resident !== null) {
            $this->forceFill(['resident_id' => $resident->id])->saveQuietly();
        }

        return $resident;
    }
}
