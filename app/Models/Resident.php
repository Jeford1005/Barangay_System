<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Resident extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_ARCHIVED = 'Archived';

    public const SEXES = ['Male', 'Female', 'Other'];
    public const CIVIL_STATUSES = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];

    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'suffix',
        'birth_date', 'sex', 'civil_status', 'occupation',
        'phone', 'email', 'address', 'purok_id', 'household_id', 'status', 'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'age' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // full_name and age are denormalized columns — recompute them on write
        // so search, sorting and printouts can never drift from the source fields.
        static::saving(function (Resident $resident): void {
            $resident->full_name = trim(implode(' ', array_filter([
                $resident->first_name,
                $resident->middle_name,
                $resident->last_name,
                $resident->suffix,
            ])));

            $resident->age = $resident->birth_date
                ? (int) $resident->birth_date->age
                : $resident->age;
        });
    }

    /* ------------------------------------------------------------------ */

    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function certificateIssuances(): HasMany
    {
        return $this->hasMany(CertificateIssuance::class);
    }

    public function certificateRequests(): HasMany
    {
        return $this->hasMany(CertificateRequest::class);
    }

    public function welfareRecords(): HasMany
    {
        return $this->hasMany(Welfare::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    /** Simple directory search across name, address and phone. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (trim((string) $term) === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%';
            $q->where('full_name', 'like', $like)
                ->orWhere('address', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /** Address falls back to the household address when the field is blank. */
    public function resolvedAddress(): string
    {
        if (trim((string) $this->address) !== '') {
            return $this->address;
        }

        return $this->household?->address ?? '';
    }

    public function scopeForDirectory(Builder $query): Builder
    {
        return $query->active()->with('purok')->orderBy('last_name')->orderBy('first_name');
    }
}
