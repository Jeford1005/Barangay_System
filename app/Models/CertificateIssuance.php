<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateIssuance extends Model
{
    use HasFactory, SoftDeletes;

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
        return $this->belongsTo(Document::class);
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
}
