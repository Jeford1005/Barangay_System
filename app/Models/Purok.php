<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purok extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'description'];

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }

    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }

    /** "Purok 1 (P1)" for dropdowns. */
    public function label(): string
    {
        return "{$this->name} ({$this->code})";
    }
}
