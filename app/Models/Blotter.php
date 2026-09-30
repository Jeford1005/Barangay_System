<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Blotter extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    /**
     * Width of `blotter.case_number` after the 2026-09-30 widening (was
     * 20). The tombstone parked on soft-delete must always fit so a
     * deleted case number never blocks reuse of the original value.
     */
    public const CODE_MAX = 40;

    /**
     * Marker parked after the original case number on soft-delete. Case
     * numbers are server-generated (`BLTR-YYYY-NNNN`), so the marker
     * round-trips unambiguously on restore.
     */
    public const DELETED_MARKER = '#DEL';

    protected $table = 'blotter';

    protected $fillable = [
        'case_number',
        'complainant_id',
        'complainant_name',
        'complainant_address',
        'complainant_phone',
        'accused_id',
        'accused_name',
        'accused_address',
        'accused_phone',
        'complaint_type',
        'complaint_subtype',
        'complaint_date',
        'complaint_time',
        'alleged_offense',
        'status',
        'reported_by_resident',
        'disposition',
        'disposition_date',
        'arrest_made',
        'investigator',
        'officer_id',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'complainant_id' => 'integer',
        'accused_id' => 'integer',
        'officer_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'complaint_date' => 'date',
        'disposition_date' => 'date',
        'complaint_time' => 'datetime:H:i',
        'reported_by_resident' => 'boolean',
    ];

    public function complainant()
    {
        return $this->belongsTo(Resident::class, 'complainant_id')->withTrashed();
    }

    public function accused()
    {
        return $this->belongsTo(Resident::class, 'accused_id')->withTrashed();
    }

    public function officer()
    {
        // The handling officer may be archived while the case stays on
        // record; keep showing the officer instead of nulling the relation.
        return $this->belongsTo(Official::class, 'officer_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'Open');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    protected static function booted(): void
    {
        // The UNIQUE index on case_number also covers soft-deleted rows, so
        // archiving a case would otherwise block its number forever. Park a
        // tombstone on soft-delete (freeing the original for reuse) and
        // reclaim the original on restore when it is still free.
        static::deleting(function (Blotter $blotter): void {
            if ($blotter->isForceDeleting()) {
                return;
            }

            $code = (string) ($blotter->getOriginal('case_number') ?? $blotter->case_number);

            if ($code === '' || str_contains($code, self::DELETED_MARKER)) {
                return;
            }

            $suffix = self::DELETED_MARKER.$blotter->getKey();
            $blotter->case_number = substr($code, 0, max(0, self::CODE_MAX - strlen($suffix))).$suffix;

            if ($blotter->case_number === '' || strlen($suffix) > self::CODE_MAX) {
                $blotter->case_number = substr($suffix, -self::CODE_MAX);
            }

            $blotter->saveQuietly();
        });

        static::restoring(function (Blotter $blotter): void {
            $code = (string) $blotter->case_number;
            $pos = strpos($code, self::DELETED_MARKER);

            if ($pos === false || $pos === 0) {
                return;
            }

            $original = substr($code, 0, $pos);

            $taken = static::query()
                ->where('case_number', $original)
                ->whereKeyNot($blotter->getKey())
                ->exists();

            if (! $taken) {
                $blotter->case_number = $original;
            }
        });
    }

    /**
     * Date-free `H:i` representation of the TIME column. The
     * `datetime:H:i` cast hydrates a Carbon instance stamped with today's
     * date (harmless for display, misleading for comparisons/exports), so
     * use this accessor when only the wall-clock time matters.
     */
    public function getComplaintTimeShortAttribute(): ?string
    {
        return $this->complaint_time?->format('H:i');
    }

    /**
     * Normalize every boolean-ish input to the stored Yes/No enum so the
     * column only ever holds the two values the reports, the audit, and
     * the print sheet expect. Unknown strings pass through untouched so
     * the data-quality audit can still flag them instead of silently
     * laundering them into a valid value.
     */
    public function setArrestMadeAttribute(mixed $value): void
    {
        $this->attributes['arrest_made'] = self::normalizeArrestMade($value);
    }

    public static function normalizeArrestMade(mixed $value): string
    {
        if (in_array($value, [true, 1, '1', 'yes', 'Yes', 'YES', 'Y', 'y'], true)) {
            return 'Yes';
        }

        if (in_array($value, [false, 0, '0', 'no', 'No', 'NO', 'N', 'n', null, ''], true)) {
            return 'No';
        }

        return is_string($value) ? $value : 'No';
    }

    /**
     * Boolean view of arrest_made for application code; the stored column
     * stays Yes/No for the views, reports, and audit expectations.
     */
    public function getWasArrestMadeAttribute(): bool
    {
        return ($this->attributes['arrest_made'] ?? 'No') === 'Yes';
    }

    public function setWasArrestMadeAttribute(mixed $value): void
    {
        $this->attributes['arrest_made'] = self::normalizeArrestMade($value);
    }
}
