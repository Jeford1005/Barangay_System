<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resident extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'residents';

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'birth_date',
        'birthplace',
        'sex',
        'civil_status',
        'nationality',
        'religion',
        'education_level',
        'occupation',
        'spouse_name',
        'blood_type',
        'phone_number',
        'email',
        'address',
        'photo',
        'residency_status',
        'voter_status',
        'is_household_head',
        'status',
        'purok_id',
        'household_id',
        'user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'voter_status' => 'boolean',
        'is_household_head' => 'boolean',
        'purok_id' => 'integer',
        'household_id' => 'integer',
        'user_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'status' => 'string',
        'residency_status' => 'string',
    ];

    public function setNationalityAttribute($value): void
    {
        $this->attributes['nationality'] = blank($value) ? 'Filipino' : $value;
    }

    public function purok()
    {
        return $this->belongsTo(Purok::class, 'purok_id');
    }

    public function household()
    {
        return $this->belongsTo(Household::class, 'household_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function getFullNameAttribute()
    {
        $name = $this->first_name;
        if ($this->middle_name) {
            $name .= ' ' . $this->middle_name;
        }
        $name .= ' ' . $this->last_name;
        if ($this->suffix) {
            $name .= ' ' . $this->suffix;
        }
        return $name;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }
}