<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

/**
 * Append-only audit trail. Record who did what to which entity, with the
 * before/after values, for the admin-only audit log screen.
 *
 *   AuditLog::record('approved', 'user', $user->id, $before, $after);
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id',
        'before', 'after', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string $action,
        string $entityType,
        int|string|null $entityId = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? (int) $entityId : null,
            'before' => $before,
            'after' => $after,
            'ip_address' => Request::ip(),
            'user_agent' => str(Request::userAgent() ?? '')->limit(250, ''),
            'created_at' => now(),
        ]);
    }
}
