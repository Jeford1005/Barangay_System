<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'resident_id',
        'document_id',
        'purpose',
        'copies',
        'status',
        'issuance_id',
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
        return $this->belongsTo(Document::class);
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
}
