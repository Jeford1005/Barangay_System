<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::query()->latest('occurred_at');

        if ($request->filled('event')) {
            $query->where('event', $request->string('event'));
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
                    ->orWhere('ip_address', 'like', '%'.$search.'%');
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('admin.audit-logs', [
            'logs' => $logs,
            'events' => AuditLog::select('event')->distinct()->orderBy('event')->pluck('event'),
        ]);
    }
}
