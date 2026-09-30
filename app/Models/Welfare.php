<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Welfare extends Model
{
    use HasFactory, SoftDeletes, Searchable;

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
     * Whitelisted status transitions for the assistance workflow.
     * Same-status edits (field corrections) are always allowed; anything not
     * listed here is rejected by the controller together with the committed-
     * money guard there. The properties that matter: release is reachable
     * solely through approval, and denied rows cannot jump straight back
     * into money states — they reopen through Requested.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        'Requested' => ['Requested', 'Under Review', 'Approved', 'Denied'],
        'Under Review' => ['Under Review', 'Requested', 'Approved', 'Denied'],
        'Approved' => ['Approved', 'Under Review', 'Requested', 'Released', 'Denied'],
        'Denied' => ['Denied', 'Requested', 'Under Review', 'Approved'],
        'Released' => ['Released', 'Requested', 'Under Review', 'Approved'],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Total approved-but-not-yet-released assistance.
     */
    public function scopePendingRelease($query)
    {
        return $query->where('status', 'Approved');
    }
}
