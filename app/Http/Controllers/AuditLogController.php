<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Concerns\Searchable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /**
     * Earliest plausible log date. Mirrors the after_or_equal:1900-01-01
     * bound the report date ranges enforce; keep them in sync.
     */
    private const MIN_FILTER_YEAR = 1900;

    public function index(Request $request): View
    {
        // Filter dropdown sources first: the same cached DISTINCT scan that
        // feeds the selects doubles as the whitelist for the event and
        // record-type filters, so the options and the accepted values can
        // never disagree.
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

        $query = AuditLog::query()->latest('occurred_at');

        // Every string filter is whitelisted before it touches the query —
        // the same filled()+in_array() guard the other admin indexes
        // (blotter, welfare, residents) use — so an unknown value applies no
        // filtering instead of narrowing the log to an arbitrary slice.
        if ($request->filled('event') && in_array((string) $request->string('event'), $filters['events'], true)) {
            $query->where('event', (string) $request->string('event'));
        }

        if ($request->filled('actor_type') && in_array((string) $request->string('actor_type'), ['user', 'system', 'guest'], true)) {
            $query->where('actor_type', (string) $request->string('actor_type'));
        }

        if ($request->filled('subject_type') && in_array((string) $request->string('subject_type'), $filters['subjectTypes'], true)) {
            $query->where('subject_type', (string) $request->string('subject_type'));
        }

        // Dates must be real Y-m-d calendar days — the shape the date inputs
        // emit. Anything else (free text, overflows like 2026-02-30,
        // implausibly early years) is ignored rather than failing the page.
        if ($request->filled('from') && ($from = self::parseFilterDate($request->input('from')))) {
            $query->where('occurred_at', '>=', $from->startOfDay());
        }

        if ($request->filled('to') && ($to = self::parseFilterDate($request->input('to')))) {
            $query->where('occurred_at', '<=', $to->endOfDay());
        }

        if ($request->filled('actor')) {
            $actor = mb_substr(strip_tags((string) $request->input('actor')), 0, 100);
            $query->search($actor, ['actor_email', 'user_email']);
        }

        if ($request->filled('subject')) {
            $subject = mb_substr(strip_tags((string) $request->input('subject')), 0, 100);
            $query->search($subject, ['subject_label', 'user_email']);
        }

        if ($request->filled('search')) {
            $search = mb_substr(strip_tags((string) $request->search), 0, 100);
            $query->where(function ($q) use ($search) {
                $q->search($search, ['user_email', 'actor_email', 'subject_label', 'subject_type', 'ip_address']);

                if (ctype_digit((string) Searchable::normalizeSearchTerm($search))) {
                    $id = (int) $search;
                    $q->orWhere('subject_id', $id)
                        ->orWhere('actor_id', $id)
                        ->orWhere('user_id', $id);
                }

                // Actor names live on the users table, not the log row.
                $q->orWhereHas('actor', function ($builder) use ($search) {
                    $builder->search($search, ['name', 'email']);
                });
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('admin.audit-logs', [
            'logs' => $logs,
            'events' => $filters['events'],
            'subjectTypes' => $filters['subjectTypes'],
        ]);
    }

    /**
     * Strict Y-m-d filter date: the exact length of a date-input value, a
     * real calendar day (no month-13 or Feb-30 overflow), and no earlier
     * than MIN_FILTER_YEAR. Returns null for anything else so the caller
     * can ignore the filter. Well-formed future dates still apply — they
     * truthfully match nothing — only non-dates are dropped.
     */
    private static function parseFilterDate(mixed $value): ?Carbon
    {
        $text = trim((string) $value);

        if (strlen($text) !== 10) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $text, 'Asia/Manila');
        } catch (\Throwable) {
            return null;
        }

        if ($date === false || $date->format('Y-m-d') !== $text) {
            return null;
        }

        if ((int) $date->format('Y') < self::MIN_FILTER_YEAR) {
            return null;
        }

        return $date;
    }
}
