<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purok extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'created_by',
    ];

    protected $casts = [
        'created_by' => 'integer',
    ];

    public function residents()
    {
        return $this->hasMany(Resident::class);
    }

    public function households()
    {
        return $this->hasMany(Household::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}