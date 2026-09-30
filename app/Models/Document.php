<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Width of `documents.code` after the 2026-09-30 widening (was 8; the
     * form-level max of 8 for human-entered codes is unchanged). The extra
     * room holds the tombstone parked on soft-delete so a deleted code
     * never blocks reuse of the original value.
     */
    public const CODE_MAX = 16;

    /**
     * Marker parked after the original code on soft-delete. Catalog codes
     * are validated alphanumeric (`/^[A-Za-z0-9]+$/`), so the marker
     * round-trips unambiguously on restore.
     */
    public const DELETED_MARKER = '#DEL';

    protected $fillable = [
        'code',
        'title',
        'description',
        'category',
        'document_type',
        'requirements',
        'fee',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function issuances()
    {
        return $this->hasMany(CertificateIssuance::class);
    }

    public function requests()
    {
        return $this->hasMany(CertificateRequest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeCertificate($query)
    {
        return $query->whereIn('document_type', ['Certificate', 'Clearance']);
    }

    protected static function booted(): void
    {
        // The UNIQUE index on code also covers soft-deleted rows, so
        // archiving a catalog entry would otherwise block its code
        // forever. Park a tombstone on soft-delete (freeing the original
        // for reuse) and reclaim the original on restore when free.
        static::deleting(function (Document $document): void {
            if ($document->isForceDeleting()) {
                return;
            }

            $code = (string) ($document->getOriginal('code') ?? $document->code);

            if ($code === '' || str_contains($code, self::DELETED_MARKER)) {
                return;
            }

            $suffix = self::DELETED_MARKER.$document->getKey();
            $document->code = substr($code, 0, max(0, self::CODE_MAX - strlen($suffix))).$suffix;

            if ($document->code === '' || strlen($suffix) > self::CODE_MAX) {
                $document->code = substr($suffix, -self::CODE_MAX);
            }

            $document->saveQuietly();
        });

        static::restoring(function (Document $document): void {
            $code = (string) $document->code;
            $pos = strpos($code, self::DELETED_MARKER);

            if ($pos === false || $pos === 0) {
                return;
            }

            $original = substr($code, 0, $pos);

            $taken = static::query()
                ->where('code', $original)
                ->whereKeyNot($document->getKey())
                ->exists();

            if (! $taken) {
                $document->code = $original;
            }
        });
    }
}
