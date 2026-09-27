<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Welfare extends Model
{
    use HasFactory;

    public const ASSISTANCE_TYPES = ['Financial', 'Food', 'Medical', 'Educational', 'Housing', 'Other'];
    public const STATUSES = ['Requested', 'Under Review', 'Approved', 'Denied', 'Released'];

    protected $table = 'welfare';

    protected $fillable = [
        'resident_id', 'assistance_type', 'requested_amount', 'amount',
        'status', 'request_date', 'notes', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'requested_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
