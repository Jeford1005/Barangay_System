<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A frozen, issued certificate. The recipient_snapshot JSON keeps the exact
 * data printed on the paper even if the resident record changes later.
 */
class CertificateIssuance extends Model
{
    use HasFactory;

    public const STATUS_ISSUED = 'Issued';
    public const STATUS_VOIDED = 'Voided';

    protected $fillable = [
        'control_number', 'document_id', 'resident_id', 'purpose', 'fee', 'copies',
        'recipient_snapshot', 'status', 'voided_at', 'voided_by', 'void_reason',
        'issued_by', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'recipient_snapshot' => 'array',
            'fee' => 'decimal:2',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    /** Build the snapshot that gets frozen at issuance time. */
    public static function snapshotFor(Resident $resident): array
    {
        return [
            'full_name' => $resident->full_name,
            'age' => $resident->age,
            'sex' => $resident->sex,
            'civil_status' => $resident->civil_status,
            'address' => $resident->resolvedAddress(),
            'purok_name' => $resident->purok?->name,
        ];
    }
}
