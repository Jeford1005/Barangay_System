<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A certificate/permit/clearance type in the barangay catalog. */
class Document extends Model
{
    use HasFactory;

    public const TYPES = ['Certificate', 'Permit', 'Clearance', 'ID', 'Other'];
    public const STATUSES = ['Active', 'Inactive', 'Draft'];

    protected $fillable = [
        'code', 'title', 'description', 'document_type', 'fee', 'status',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
        ];
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(CertificateIssuance::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(CertificateRequest::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Active');
    }
}
