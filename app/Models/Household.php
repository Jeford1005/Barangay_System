<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    /**
     * Width of `households.household_code` after the 2026-09-30 widening
     * (was 20). The tombstone parked on soft-delete must always fit so a
     * deleted code never blocks reuse of the original value.
     */
    public const CODE_MAX = 40;

    /**
     * Marker parked after the original code on soft-delete. Human-entered
     * codes never contain '#', and generated codes are alphanumeric, so the
     * marker round-trips unambiguously on restore.
     */
    public const DELETED_MARKER = '#DEL';

    protected $fillable = [
        'household_code',
        'sitio',
        'street',
        'purok_id',
        'barangay',
        'municipality',
        'province',
        'region',
        'zip_code',
        'house_type',
        'lot_area',
        'floor_area',
        'year_built',
        'ownership',
        'num_members',
        'head_of_household_id',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'purok_id' => 'integer',
        'year_built' => 'integer',
        'num_members' => 'integer',
        'head_of_household_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function purok()
    {
        return $this->belongsTo(Purok::class);
    }

    public function head()
    {
        // The head may be archived while the household stays live; keep
        // showing the name instead of dropping the relation to null.
        return $this->belongsTo(Resident::class, 'head_of_household_id')->withTrashed();
    }

    public function residents()
    {
        return $this->hasMany(Resident::class, 'household_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected static function booted(): void
    {
        // The UNIQUE index on household_code also covers soft-deleted rows,
        // so archiving a household would otherwise block its code forever.
        // Park a tombstone on soft-delete (freeing the original for reuse)
        // and reclaim the original on restore when it is still free.
        static::deleting(function (Household $household): void {
            if ($household->isForceDeleting()) {
                return;
            }

            $code = (string) ($household->getOriginal('household_code') ?? $household->household_code);

            if ($code === '' || str_contains($code, self::DELETED_MARKER)) {
                return;
            }

            $suffix = self::DELETED_MARKER.$household->getKey();
            $household->household_code = substr($code, 0, max(0, self::CODE_MAX - strlen($suffix))).$suffix;

            if ($household->household_code === '' || strlen($suffix) > self::CODE_MAX) {
                $household->household_code = substr($suffix, -self::CODE_MAX);
            }

            $household->saveQuietly();
        });

        static::restoring(function (Household $household): void {
            $code = (string) $household->household_code;
            $pos = strpos($code, self::DELETED_MARKER);

            if ($pos === false || $pos === 0) {
                return;
            }

            $original = substr($code, 0, $pos);

            $taken = static::query()
                ->where('household_code', $original)
                ->whereKeyNot($household->getKey())
                ->exists();

            if (! $taken) {
                $household->household_code = $original;
            }
        });
    }
}