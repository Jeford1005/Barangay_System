<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The append-only audit trail: every create, update, approval, void and
 * export recorded by the system, filterable by action, entity, day and
 * free text.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('search')), 0, 100);
        $action = mb_substr(trim((string) $request->query('action')), 0, 60);
        $entityType = mb_substr(trim((string) $request->query('entity_type')), 0, 60);
        $date = trim((string) $request->query('date'));

        // Only accept an ISO day — anything else is silently dropped.
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = '';
        }

        $query = AuditLog::query()->with('user');

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($entityType !== '') {
            $query->where('entity_type', $entityType);
        }

        if ($date !== '') {
            $query->whereDate('created_at', $date);
        }

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

            $query->where(function ($q) use ($like): void {
                $q->where('action', 'like', $like)
                    ->orWhere('entity_type', 'like', $like)
                    ->orWhere('ip_address', 'like', $like)
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like));
            });
        }

        $logs = $query
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'actions' => AuditLog::query()
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'entityTypes' => AuditLog::query()
                ->distinct()
                ->orderBy('entity_type')
                ->pluck('entity_type'),
            'filters' => [
                'search' => $search,
                'action' => $action,
                'entity_type' => $entityType,
                'date' => $date,
            ],
        ]);
    }
}
