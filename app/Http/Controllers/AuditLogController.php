<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::query()->latest('occurred_at');

        if ($request->filled('event')) {
            $query->where('event', $request->string('event'));
        }

        if ($request->filled('actor_type') && in_array((string) $request->string('actor_type'), ['user', 'system', 'guest'], true)) {
            $query->where('actor_type', (string) $request->string('actor_type'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', (string) $request->string('subject_type'));
        }

        if ($request->filled('from')) {
            try {
                $query->where('occurred_at', '>=', Carbon::parse((string) $request->input('from'))->startOfDay());
            } catch (\Throwable) {
                // An unparseable date is ignored rather than failing the page.
            }
        }

        if ($request->filled('to')) {
            try {
                $query->where('occurred_at', '<=', Carbon::parse((string) $request->input('to'))->endOfDay());
            } catch (\Throwable) {
                // An unparseable date is ignored rather than failing the page.
            }
        }

        if ($request->filled('actor')) {
            $actor = mb_substr(strip_tags((string) $request->input('actor')), 0, 100);
            $query->where(function ($builder) use ($actor) {
                $builder->where('actor_email', 'like', "%{$actor}%")
                    ->orWhere('user_email', 'like', "%{$actor}%");
            });
        }

        if ($request->filled('subject')) {
            $subject = mb_substr(strip_tags((string) $request->input('subject')), 0, 100);
            $query->where(function ($builder) use ($subject) {
                $builder->where('subject_label', 'like', "%{$subject}%")
                    ->orWhere('user_email', 'like', "%{$subject}%");
            });
        }

        if ($request->filled('search')) {
            $search = mb_substr(strip_tags((string) $request->search), 0, 100);
            $query->where(function ($q) use ($search) {
                $q->where('user_email', 'like', '%'.$search.'%')
                    ->orWhere('actor_email', 'like', '%'.$search.'%')
                    ->orWhere('subject_label', 'like', '%'.$search.'%')
                    ->orWhere('subject_type', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%');

                if (ctype_digit($search)) {
                    $id = (int) $search;
                    $q->orWhere('subject_id', $id)
                        ->orWhere('actor_id', $id)
                        ->orWhere('user_id', $id);
                }

                // Actor names live on the users table, not the log row.
                $q->orWhereHas('actor', function ($builder) use ($search) {
                    $builder->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        // Filter dropdowns: one narrow DISTINCT scan over (event,
        // subject_type) instead of two full scans, cached briefly so every
        // page of the log recounts nothing.
        $filters = Cache::remember('audit-log.filters', now()->addSeconds(45), function () {
            $pairs = AuditLog::query()
                ->select(['event', 'subject_type'])
                ->distinct()
                ->get();

            return [
                'events' => $pairs->pluck('event')->filter()->unique()->sort()->values()->all(),
                'subjectTypes' => $pairs->pluck('subject_type')->filter()->unique()->sort()->values()->all(),
            ];
        });

        return view('admin.audit-logs', [
            'logs' => $logs,
            'events' => $filters['events'],
            'subjectTypes' => $filters['subjectTypes'],
        ]);
    }
}
