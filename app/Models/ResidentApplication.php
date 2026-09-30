<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResidentApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birth_date',
        'sex',
        'civil_status',
        'phone_number',
        'email',
        'address',
        'purok_id',
        'household_id',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'purok_id' => 'integer',
        'household_id' => 'integer',
        'reviewed_by' => 'integer',
        'birth_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function purok()
    {
        return $this->belongsTo(Purok::class);
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }
}
