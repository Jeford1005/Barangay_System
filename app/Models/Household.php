<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'household_code',
        'sitio',
        'street',
        'purok_id',
        'barangay',
        'municipality',
        'province',
        'region',
        'zip_code',
        'house_type',
        'lot_area',
        'floor_area',
        'year_built',
        'ownership',
        'num_members',
        'head_of_household_id',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'purok_id' => 'integer',
        'year_built' => 'integer',
        'num_members' => 'integer',
        'head_of_household_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function purok()
    {
        return $this->belongsTo(Purok::class);
    }

    public function head()
    {
        return $this->belongsTo(Resident::class, 'head_of_household_id');
    }

    public function residents()
    {
        return $this->hasMany(Resident::class, 'household_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}