<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Blotter extends Model
{
    use HasFactory;

    public const STATUSES = ['Open', 'Pending', 'Resolved', 'Dismissed'];
    public const ARREST_OPTIONS = ['No', 'Yes'];

    protected $table = 'blotter';

    protected $fillable = [
        'case_number', 'incident_date', 'incident_time', 'incident_type', 'location',
        'purok_id', 'complainant_name', 'complainant_contact',
        'respondent_name', 'respondent_contact', 'narrative',
        'handling_officer', 'arrest_made', 'status', 'resolution_notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'incident_time' => 'date:H:i:s',
        ];
    }

    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (trim((string) $term) === '') {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('case_number', 'like', $like)
                ->orWhere('incident_type', 'like', $like)
                ->orWhere('complainant_name', 'like', $like)
                ->orWhere('respondent_name', 'like', $like);
        });
    }
}
