<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CleanupParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'drive_id',
        'resident_id',
        'attended',
        'hours',
        'checked_in_at',
    ];

    protected $casts = [
        'drive_id' => 'integer',
        'resident_id' => 'integer',
        'attended' => 'boolean',
        'hours' => 'decimal:1',
        'checked_in_at' => 'datetime',
    ];

    public function drive()
    {
        // The drive may be archived while its sign-up history stays on
        // record; keep the link instead of nulling it.
        return $this->belongsTo(CleanupDrive::class, 'drive_id')->withTrashed();
    }

    public function resident()
    {
        // The resident may be archived while the sign-up stays on record;
        // keep showing the resident instead of dropping the relation.
        return $this->belongsTo(Resident::class, 'resident_id')->withTrashed();
    }
}
