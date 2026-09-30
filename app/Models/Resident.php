<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class Resident extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $table = 'residents';

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'birth_date',
        'birthplace',
        'sex',
        'civil_status',
        'nationality',
        'religion',
        'education_level',
        'occupation',
        'spouse_name',
        'blood_type',
        'phone_number',
        'email',
        'address',
        'photo',
        'residency_status',
        'voter_status',
        'is_household_head',
        'status',
        'purok_id',
        'household_id',
        'user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'voter_status' => 'boolean',
        'is_household_head' => 'boolean',
        'purok_id' => 'integer',
        'household_id' => 'integer',
        'user_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'status' => 'string',
        'residency_status' => 'string',
    ];

    public function setNationalityAttribute($value): void
    {
        $this->attributes['nationality'] = blank($value) ? 'Filipino' : $value;
    }

    public function purok()
    {
        return $this->belongsTo(Purok::class, 'purok_id');
    }

    public function household()
    {
        // A resident keeps its household link for display even after the
        // household itself is archived.
        return $this->belongsTo(Household::class, 'household_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function getFullNameAttribute()
    {
        $name = $this->first_name;
        if ($this->middle_name) {
            $name .= ' ' . $this->middle_name;
        }
        $name .= ' ' . $this->last_name;
        if ($this->suffix) {
            $name .= ' ' . $this->suffix;
        }
        return $name;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    /**
     * Canonical uniqueness rule for residents.email: unique among live
     * rows, ignoring the record being updated. Mirrors the users.email
     * UNIQUE semantics (one live owner per address) while letting an
     * archived profile keep its address and freeing it for reuse.
     */
    public static function emailUniqueRule(?int $ignoreId = null): Unique
    {
        return Rule::unique('residents', 'email')->ignore($ignoreId)->whereNull('deleted_at');
    }

    /**
     * Canonical uniqueness rule for the 1:1 user account link. A resident
     * profile belongs to at most one user account and vice versa.
     */
    public static function userIdUniqueRule(?int $ignoreId = null): Unique
    {
        return Rule::unique('residents', 'user_id')->ignore($ignoreId)->whereNull('deleted_at');
    }

    /**
     * Application-level check for the 1:1 link. There is deliberately no
     * hard UNIQUE index on residents.user_id: legacy duplicates are
     * tolerated and reported (not destroyed) by
     * `residents:check-account-integrity` and `data:quality-audit`, and a
     * database constraint would break the archival and approval flows that
     * move accounts between profiles.
     */
    public static function isUserIdAvailable(?int $userId, ?int $ignoreId = null): bool
    {
        if ($userId === null) {
            return true;
        }

        return ! static::query()
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}