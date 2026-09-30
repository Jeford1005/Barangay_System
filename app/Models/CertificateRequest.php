<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CertificateRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'resident_id',
        'document_id',
        'purpose',
        'copies',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'resident_id' => 'integer',
        'document_id' => 'integer',
        'copies' => 'integer',
        'issuance_id' => 'integer',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function resident()
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    public function document()
    {
        // The catalog entry may be archived while its requests stay on
        // record; keep showing the document instead of nulling it.
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function issuance()
    {
        return $this->belongsTo(CertificateIssuance::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    protected static function booted(): void
    {
        // Model-level guard (not controller-level): a resident may hold at
        // most one Pending request per certificate type. Non-Pending rows
        // (Approved, Rejected, Cancelled) never block a new request. The
        // partial unique index from the 2026-09-30 migration enforces the
        // same rule in the database where the driver supports it.
        static::creating(function (CertificateRequest $request): void {
            $status = $request->status ?? 'Pending';

            if ($status !== 'Pending') {
                return;
            }

            if ($request->resident_id && $request->document_id
                && static::hasPendingDuplicate((int) $request->resident_id, (int) $request->document_id)) {
                throw ValidationException::withMessages([
                    'document_id' => 'This resident already has a pending request for this certificate type.',
                ]);
            }
        });
    }

    /**
     * Whether a Pending request already exists for the same
     * resident+document pair. Used by the creating guard above and
     * available to any caller that needs the check without creating.
     */
    public static function hasPendingDuplicate(int $residentId, int $documentId, ?int $ignoreId = null): bool
    {
        return static::query()
            ->where('resident_id', $residentId)
            ->where('document_id', $documentId)
            ->where('status', 'Pending')
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
