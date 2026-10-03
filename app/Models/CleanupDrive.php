<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CleanupDrive extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    /**
     * Every status the workflow knows. Kept as a constant (not a hard DB
     * enum) so the controller, the export, and the archive whitelist can
     * never drift apart.
     *
     * @var list<string>
     */
    public const STATUSES = ['Scheduled', 'Ongoing', 'Completed', 'Cancelled'];

    /**
     * Whitelisted status transitions for the cleanup workflow.
     * Same-status edits (field corrections) are always allowed; anything not
     * listed here is rejected by the controller.
     *
     * Completed is terminal with NO reopen path: a finished drive is
     * history, so the controller blocks every edit once Completed — not
     * just status changes. Cancelled is terminal for status moves (a
     * called-off drive never resumes), though its descriptive fields stay
     * correctable through same-status edits.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        'Scheduled' => ['Scheduled', 'Ongoing', 'Cancelled'],
        'Ongoing' => ['Ongoing', 'Completed', 'Cancelled'],
        'Completed' => ['Completed'],
        'Cancelled' => ['Cancelled'],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    protected $fillable = [
        'title',
        'description',
        'purok_id',
        'scheduled_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'purok_id' => 'integer',
        'scheduled_at' => 'datetime',
        'created_by' => 'integer',
    ];

    public function purok()
    {
        // A purok may be archived while its drives stay on record; keep
        // showing the purok instead of dropping the relation to null.
        return $this->belongsTo(Purok::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants()
    {
        return $this->hasMany(CleanupParticipant::class, 'drive_id');
    }

    public function residents()
    {
        return $this->belongsToMany(Resident::class, 'cleanup_participants', 'drive_id', 'resident_id')
            ->withPivot(['attended', 'hours', 'checked_in_at'])
            ->withTimestamps();
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'Scheduled');
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'Ongoing');
    }
}
