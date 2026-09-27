<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Welfare extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'welfare';

    protected $fillable = [
        'beneficiary_id',
        'beneficiary_name',
        'beneficiary_address',
        'beneficiary_phone',
        'assistance_type',
        'program_name',
        'program_description',
        'requested_amount',
        'approved_amount',
        'status',
        'request_date',
        'approval_date',
        'release_date',
        'remarks',
    ];

    protected $casts = [
        'beneficiary_id' => 'integer',
        'request_date' => 'date',
        'approval_date' => 'date',
        'release_date' => 'date',
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
    ];

    public function beneficiary()
    {
        return $this->belongsTo(Resident::class, 'beneficiary_id')->withTrashed();
    }

    public function scopeRequested($query)
    {
        return $query->where('status', 'Requested');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'Under Review');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'Approved');
    }

    /**
     * Total approved-but-not-yet-released assistance.
     */
    public function scopePendingRelease($query)
    {
        return $query->where('status', 'Approved');
    }
}
