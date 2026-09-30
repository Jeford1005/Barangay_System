<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateIssuance extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    /**
     * Width of `certificate_issuances.control_number` after the 2026-09-30
     * widening (was 30). The tombstone parked on soft-delete must always
     * fit so a deleted control number never blocks reuse of the original.
     */
    public const CODE_MAX = 48;

    /**
     * Marker parked after the original control number on soft-delete.
     * Control numbers are server-generated (`CODE-YYYY-NNNN`), so the
     * marker round-trips unambiguously on restore.
     */
    public const DELETED_MARKER = '#DEL';

    protected $table = 'certificate_issuances';

    protected $fillable = [
        'control_number',
        'document_id',
        'resident_id',
        'recipient_snapshot',
        'document_snapshot',
        'purpose',
        'copies',
        'fee',
        'status',
        'remarks',
        'issued_by',
        'voided_by',
        'voided_at',
        'created_by',
    ];

    protected $casts = [
        'document_id' => 'integer',
        'resident_id' => 'integer',
        'recipient_snapshot' => 'array',
        'document_snapshot' => 'array',
        'copies' => 'integer',
        'issued_by' => 'integer',
        'voided_by' => 'integer',
        'created_by' => 'integer',
        'fee' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    public function document()
    {
        // The catalog entry may be archived while issued certificates stay
        // on record; keep showing the document instead of nulling it.
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function residentSnapshot(Resident $resident): array
    {
        return [
            'full_name' => $resident->full_name,
            'age' => $resident->age,
            'sex' => $resident->sex,
            'civil_status' => $resident->civil_status,
            'birth_date' => optional($resident->birth_date)->toDateString(),
            'address' => $resident->address,
            'purok_name' => $resident->purok?->name,
        ];
    }

    public static function documentSnapshot(Document $document): array
    {
        return [
            'code' => $document->code,
            'title' => $document->title,
            'description' => $document->description,
            'requirements' => $document->requirements,
        ];
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'Issued');
    }

    public function scopeVoided($query)
    {
        return $query->where('status', 'Voided');
    }

    protected static function booted(): void
    {
        // The UNIQUE index on control_number also covers soft-deleted rows,
        // so voiding/archiving an issuance would otherwise block its number
        // forever. Park a tombstone on soft-delete (freeing the original
        // for reuse) and reclaim the original on restore when free.
        static::deleting(function (CertificateIssuance $issuance): void {
            if ($issuance->isForceDeleting()) {
                return;
            }

            $code = (string) ($issuance->getOriginal('control_number') ?? $issuance->control_number);

            if ($code === '' || str_contains($code, self::DELETED_MARKER)) {
                return;
            }

            $suffix = self::DELETED_MARKER.$issuance->getKey();
            $issuance->control_number = substr($code, 0, max(0, self::CODE_MAX - strlen($suffix))).$suffix;

            if ($issuance->control_number === '' || strlen($suffix) > self::CODE_MAX) {
                $issuance->control_number = substr($suffix, -self::CODE_MAX);
            }

            $issuance->saveQuietly();
        });

        static::restoring(function (CertificateIssuance $issuance): void {
            $code = (string) $issuance->control_number;
            $pos = strpos($code, self::DELETED_MARKER);

            if ($pos === false || $pos === 0) {
                return;
            }

            $original = substr($code, 0, $pos);

            $taken = static::query()
                ->where('control_number', $original)
                ->whereKeyNot($issuance->getKey())
                ->exists();

            if (! $taken) {
                $issuance->control_number = $original;
            }
        });
    }
}
