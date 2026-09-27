<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    use HasFactory;

    public const HOUSE_TYPES = ['Single', 'Duplex', 'Apartment', 'Townhouse', 'Other'];
    public const OWNERSHIPS = ['Owned', 'Rented', 'Leased', 'Occupied'];
    public const STATUSES = ['Occupied', 'Vacant', 'Under Construction'];

    protected $fillable = [
        'household_number', 'address', 'purok_id', 'head_resident_id',
        'house_type', 'ownership', 'status', 'member_count',
    ];

    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'head_resident_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Resident::class, 'household_id');
    }

    /** Next household number in HH-### format. */
    public static function nextNumber(): string
    {
        $last = static::orderByDesc('id')->value('household_number');

        $n = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $n = (int) $m[1] + 1;
        }

        return sprintf('HH-%03d', $n);
    }

    /**
     * Re-derive member_count from the members table (source of truth).
     * Called by the controller after any membership/head change.
     */
    public function syncMemberCount(): void
    {
        $count = $this->members()->count();

        if ($count !== $this->member_count) {
            $this->forceFill(['member_count' => $count])->save();
        }
    }
}
