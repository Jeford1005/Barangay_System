<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Blotter extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'blotter';

    protected $fillable = [
        'case_number',
        'complainant_id',
        'complainant_name',
        'complainant_address',
        'complainant_phone',
        'accused_id',
        'accused_name',
        'accused_address',
        'accused_phone',
        'complaint_type',
        'complaint_subtype',
        'complaint_date',
        'complaint_time',
        'alleged_offense',
        'status',
        'reported_by_resident',
        'disposition',
        'disposition_date',
        'arrest_made',
        'investigator',
        'officer_id',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'complainant_id' => 'integer',
        'accused_id' => 'integer',
        'officer_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'complaint_date' => 'date',
        'disposition_date' => 'date',
        'complaint_time' => 'datetime:H:i',
        'reported_by_resident' => 'boolean',
    ];

    public function complainant()
    {
        return $this->belongsTo(Resident::class, 'complainant_id')->withTrashed();
    }

    public function accused()
    {
        return $this->belongsTo(Resident::class, 'accused_id')->withTrashed();
    }

    public function officer()
    {
        return $this->belongsTo(Official::class, 'officer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'Open');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }
}
