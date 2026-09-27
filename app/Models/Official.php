<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Official extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birth_date',
        'sex',
        'office',
        'position',
        'barangay',
        'municipality',
        'province',
        'region',
        'zip_code',
        'phone_number',
        'email',
        'photo',
        'sign_image',
        'term_start',
        'term_end',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'birth_date' => 'date',
        'term_start' => 'date',
        'term_end' => 'date',
    ];

    public function blotterCases()
    {
        return $this->hasMany(Blotter::class, 'officer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
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

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}
