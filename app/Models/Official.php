<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Official extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_INACTIVE = 'Inactive';

    protected $fillable = [
        'full_name', 'position', 'term_start', 'term_end', 'contact', 'status',
    ];

    protected function casts(): array
    {
        return [
            'term_start' => 'date',
            'term_end' => 'date',
        ];
    }

    /** The Punong Barangay signs certificates (fallback to an empty line). */
    public static function punongBarangay(): ?static
    {
        return static::where('position', 'Punong Barangay')
            ->where('status', self::STATUS_ACTIVE)
            ->orderByDesc('term_start')
            ->first();
    }
}
