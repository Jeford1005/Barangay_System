<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

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
}
