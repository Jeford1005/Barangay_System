<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purok extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $fillable = [
        'name',
        'code',
        'created_by',
    ];

    protected $casts = [
        'created_by' => 'integer',
        'updated_by' => 'integer',
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