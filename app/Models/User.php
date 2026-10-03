<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Searchable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_STAFF = 'staff';

    public const ROLE_OFFICIAL = 'official';

    public const ROLE_RESIDENT = 'resident';

    /**
     * Account approval states stored in the `status` column. Suspension is a
     * separate flag (`suspended_at`), never a status value — `statusLabel()`
     * is the single source of truth for what the UI shows.
     */
    public const ACCOUNT_STATUSES = ['pending', 'approved', 'rejected'];

    /**
     * Virtual filter/display value for suspended accounts. It is not stored
     * in `status`; see `statusLabel()`.
     */
    public const SUSPENDED_FILTER = 'suspended';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            // The TOTP secret is encrypted at rest; the recovery-code column
            // holds a JSON array of single-use bcrypt hashes.
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
            'email_otp_fallback' => 'boolean',
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

    /**
     * Display status for badges and filters. A suspended account reads as
     * Suspended regardless of its stored approval status — one source of
     * truth shared by the directory, the detail page, and the status filter.
     */
    public function statusLabel(): string
    {
        return $this->isSuspended() ? 'Suspended' : ucfirst((string) $this->status);
    }

    /**
     * Badge colors matching `statusLabel()`, so every status pill agrees.
     */
    public function statusBadgeClasses(): string
    {
        if ($this->isSuspended()) {
            return 'bg-slate-100 text-slate-700';
        }

        return match ($this->status) {
            'approved' => 'bg-emerald-50 text-emerald-700',
            'pending' => 'bg-amber-50 text-amber-700',
            default => 'bg-red-50 text-red-700',
        };
    }

    /**
     * Status filter options for the account directory. Single source for the
     * controller filter and the view dropdown — they must never drift apart.
     *
     * @return array<string, string>
     */
    public static function statusFilterOptions(): array
    {
        return [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            self::SUSPENDED_FILTER => 'Suspended',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->user_type === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return $this->user_type === self::ROLE_STAFF;
    }

    public function isOfficial(): bool
    {
        return $this->user_type === self::ROLE_OFFICIAL;
    }

    public function isOfficeUser(): bool
    {
        return $this->isAdmin() || $this->isStaff() || $this->isOfficial();
    }

    /**
     * True for office roles with CONFIRMED two-factor enrollment, in EITHER
     * mode: authenticator-app (TOTP secret present) or printed login card
     * (paper-only: confirmed_at set, secret null, recovery hashes on file).
     * Residents are always password-only, so this never returns true for
     * them even if 2FA columns were somehow present.
     *
     * DELIBERATE BEHAVIOR CHANGE (paper-first 2FA): this used to also
     * require a non-null secret. Paper-only enrollment confirms with no
     * secret, so confirmation alone is now the enabled marker and the mode
     * is derived from whether a secret exists (see isPaperTwoFactor()).
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->isOfficeUser()
            && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Paper-only mode: confirmed enrollment with no TOTP secret. Derived
     * from (confirmed, secret null) — no extra boolean column. Daily login
     * for these accounts is password + the next unused login-card code.
     */
    public function isPaperTwoFactor(): bool
    {
        return $this->hasTwoFactorEnabled()
            && $this->two_factor_secret === null;
    }

    /**
     * Authenticator-app mode: confirmed enrollment with a TOTP secret.
     */
    public function hasAppTwoFactor(): bool
    {
        return $this->hasTwoFactorEnabled()
            && $this->two_factor_secret !== null;
    }

    /**
     * Unused recovery-code hashes still on the account.
     *
     * @return array<int, string>
     */
    public function remainingRecoveryCodeHashes(): array
    {
        return array_values(array_filter(
            is_array($this->two_factor_recovery_codes) ? $this->two_factor_recovery_codes : [],
            fn ($hash) => is_string($hash) && $hash !== '',
        ));
    }

    /**
     * Count of unused login-card codes still on the account. Drives the
     * low-code (<= 2) renewal warning for paper-mode sign-in.
     */
    public function remainingRecoveryCodeCount(): int
    {
        return count($this->remainingRecoveryCodeHashes());
    }

    public function roleLabel(): string
    {
        return match ($this->user_type) {
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_STAFF => 'Staff',
            self::ROLE_OFFICIAL => 'Official',
            self::ROLE_RESIDENT => 'Resident',
            default => ucfirst((string) $this->user_type),
        };
    }

    /**
     * Central permission map for the fixed roles.
     *
     * Administrators intentionally receive every permission. Officials review
     * and decide: they read every operational module, record blotter cases and
     * settle the three approval queues, but never enter clerical records and
     * never delete anything. Staff receive only day-to-day operational
     * permissions - including intake and issuing, but no decisions. Both
     * administrator-only controls are therefore reached only through `admin`.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isOfficial()) {
            return in_array($permission, [
                'operations.access',
                'dashboard.view',
                'residents.view',
                'households.view',
                'puroks.view',
                'blotter.view',
                'blotter.manage',
                'welfare.view',
                'welfare.approve',
                'certificates.view',
                'cleanup.view',
                'certificate-requests.view',
                'certificate-requests.decide',
                'resident-changes.view',
                'resident-changes.decide',
                'reports.view',
                'analytics.view',
            ], true);
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
            'cleanup.view',
            'cleanup.manage',
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
