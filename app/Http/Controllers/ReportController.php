<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\Welfare;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Live-computed barangay reports: population, blotter and welfare.
 *
 * Every figure is queried on the fly (nothing is cached) and every report is
 * printable through Tailwind `print:` variants plus a [data-print] button.
 */
class ReportController extends Controller
{
    /** Age brackets with their exact printed labels. */
    private const AGE_BRACKETS = [
        ['label' => '0–6 (Child)', 'min' => 0, 'max' => 6],
        ['label' => '7–17 (Minor)', 'min' => 7, 'max' => 17],
        ['label' => '18–30 (Youth)', 'min' => 18, 'max' => 30],
        ['label' => '31–45 (Adult)', 'min' => 31, 'max' => 45],
        ['label' => '46–59 (Middle-aged)', 'min' => 46, 'max' => 59],
        ['label' => '60+ (Senior)', 'min' => 60, 'max' => null],
    ];

    public function index(): View
    {
        return view('reports.index');
    }

    /* ------------------------------------------------------------------ */
    /* Population                                                          */
    /* ------------------------------------------------------------------ */

    public function population(Request $request): View
    {
        [$from, $to] = $this->resolveRange($request, defaultRange: false);

        $residents = Resident::active()
            ->when($from !== null, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->get(['purok_id', 'sex', 'age']);

        $puroks = Purok::orderBy('code')->get();

        $grouped = [];
        foreach ($residents as $resident) {
            $grouped[$resident->purok_id ?? 'none'][] = $resident;
        }

        $rows = [];

        foreach ($puroks as $purok) {
            $rows[] = $this->populationLine($purok->label(), $grouped[$purok->id] ?? []);
        }

        $rows[] = $this->populationLine('No Purok Assigned', $grouped['none'] ?? []);

        $totals = ['male' => 0, 'female' => 0, 'other' => 0, 'total' => 0];
        foreach ($rows as $row) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $row[$key];
            }
        }

        $bracketCounts = array_fill_keys(array_column(self::AGE_BRACKETS, 'label'), 0);
        foreach ($residents as $resident) {
            $bracketCounts[$this->bracketLabel((int) $resident->age)]++;
        }

        $brackets = collect(self::AGE_BRACKETS)
            ->map(fn (array $bracket): array => [
                'label' => $bracket['label'],
                'count' => $bracketCounts[$bracket['label']],
                'pct' => $totals['total'] > 0
                    ? round($bracketCounts[$bracket['label']] / $totals['total'] * 100, 1)
                    : 0.0,
            ])
            ->all();

        $households = Household::query()
            ->when($from !== null, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->count();

        $this->logPrinted($request, 'population', compact('from', 'to'));

        return view('reports.population', [
            'rows' => $rows,
            'totals' => $totals,
            'brackets' => $brackets,
            'households' => $households,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Blotter                                                             */
    /* ------------------------------------------------------------------ */

    public function blotter(Request $request): View
    {
        [$from, $to] = $this->resolveRange($request, defaultRange: true);

        $cases = Blotter::query()
            ->with('purok')
            ->when($from !== null, fn ($query) => $query->whereDate('incident_date', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('incident_date', '<=', $to))
            ->orderByDesc('incident_date')
            ->orderByDesc('id')
            ->get();

        // Incident type → count + per-status breakdown, most frequent first.
        $byType = [];
        foreach ($cases as $case) {
            if (! isset($byType[$case->incident_type])) {
                $byType[$case->incident_type] = array_merge(
                    ['type' => $case->incident_type, 'total' => 0],
                    array_fill_keys(Blotter::STATUSES, 0),
                );
            }

            $byType[$case->incident_type]['total']++;

            if (isset($byType[$case->incident_type][$case->status])) {
                $byType[$case->incident_type][$case->status]++;
            }
        }
        $byType = collect($byType)->sortByDesc('total')->values();

        $statusTotals = [];
        foreach (Blotter::STATUSES as $status) {
            $statusTotals[$status] = $cases->where('status', $status)->count();
        }
        $arrests = $cases->where('arrest_made', 'Yes')->count();

        // Last 12 months (anchored on the end of the selected range), zero-filled.
        $anchor = $to !== null ? Carbon::parse($to) : now();
        $buckets = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = $anchor->copy()->startOfMonth()->subMonths($i);
            $buckets[$month->format('Y-m')] = ['label' => $month->format('M Y'), 'count' => 0];
        }
        foreach ($cases as $case) {
            $key = $case->incident_date->format('Y-m');
            if (isset($buckets[$key])) {
                $buckets[$key]['count']++;
            }
        }
        $peak = max(1, max(array_column($buckets, 'count')));
        $monthlyTrend = collect($buckets)
            ->map(fn (array $bucket): array => $bucket + [
                'pct' => round($bucket['count'] / $peak * 100, 1),
            ])
            ->values();

        $this->logPrinted($request, 'blotter', compact('from', 'to'));

        return view('reports.blotter', [
            'byType' => $byType,
            'statusTotals' => $statusTotals,
            'arrests' => $arrests,
            'totalCases' => $cases->count(),
            'monthlyTrend' => $monthlyTrend,
            'recent' => $cases->take(10)->values(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Welfare                                                             */
    /* ------------------------------------------------------------------ */

    public function welfare(Request $request): View
    {
        [$from, $to] = $this->resolveRange($request, defaultRange: true);

        $records = Welfare::query()
            ->with('resident')
            ->when($from !== null, fn ($query) => $query->whereDate('request_date', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('request_date', '<=', $to))
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->get();

        $statusTotals = [];
        foreach (Welfare::STATUSES as $status) {
            $statusTotals[$status] = $records->where('status', $status)->count();
        }

        $requestedTotal = $records->sum(fn (Welfare $record): float => (float) $record->requested_amount);
        $approvedTotal = $records
            ->filter(fn (Welfare $record): bool => in_array($record->status, ['Approved', 'Released'], true))
            ->sum(fn (Welfare $record): float => $this->granted($record));
        $releasedTotal = $records
            ->filter(fn (Welfare $record): bool => $record->status === 'Released')
            ->sum(fn (Welfare $record): float => $this->granted($record));

        $byType = $records
            ->groupBy('assistance_type')
            ->map(function ($group): array {
                $approved = $group
                    ->filter(fn (Welfare $record): bool => in_array($record->status, ['Approved', 'Released'], true));

                return [
                    'type' => $group->first()->assistance_type,
                    'count' => $group->count(),
                    'requested' => $group->sum(fn (Welfare $record): float => (float) $record->requested_amount),
                    'approved' => $approved->sum(fn (Welfare $record): float => $this->granted($record)),
                    'released' => $group
                        ->filter(fn (Welfare $record): bool => $record->status === 'Released')
                        ->sum(fn (Welfare $record): float => $this->granted($record)),
                ];
            })
            ->sortByDesc('count')
            ->values();

        $this->logPrinted($request, 'welfare', compact('from', 'to'));

        return view('reports.welfare', [
            'statusTotals' => $statusTotals,
            'totalRecords' => $records->count(),
            'requestedTotal' => $requestedTotal,
            'approvedTotal' => $approvedTotal,
            'releasedTotal' => $releasedTotal,
            'byType' => $byType,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Validate and resolve the shared ?from=&to= date range.
     *
     * Blotter/welfare default to the first day of the previous month → today;
     * the population report defaults to all time (no range at all).
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveRange(Request $request, bool $defaultRange): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date', 'before_or_equal:today'],
            'to' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        if ($from !== null && $to !== null && $to < $from) {
            throw ValidationException::withMessages([
                'to' => 'The "to" date must be on or after the "from" date.',
            ]);
        }

        if ($defaultRange && $from === null && $to === null) {
            $from = now()->startOfMonth()->subMonth()->toDateString();
            $to = now()->toDateString();
        }

        return [$from, $to];
    }

    /** One population table line: male / female / other / total. */
    /**
     * @param  array<int, \App\Models\Resident>  $residents
     * @return array{label: string, male: int, female: int, other: int, total: int}
     */
    private function populationLine(string $label, array $residents): array
    {
        $line = ['label' => $label, 'male' => 0, 'female' => 0, 'other' => 0, 'total' => 0];

        foreach ($residents as $resident) {
            $line['total']++;

            if ($resident->sex === 'Male') {
                $line['male']++;
            } elseif ($resident->sex === 'Female') {
                $line['female']++;
            } else {
                $line['other']++;
            }
        }

        return $line;
    }

    private function bracketLabel(int $age): string
    {
        foreach (self::AGE_BRACKETS as $bracket) {
            if ($age >= $bracket['min'] && ($bracket['max'] === null || $age <= $bracket['max'])) {
                return $bracket['label'];
            }
        }

        return self::AGE_BRACKETS[0]['label'];
    }

    /** Granted amount falls back to the requested amount when unset. */
    private function granted(Welfare $record): float
    {
        return (float) ($record->amount ?? $record->requested_amount ?? 0);
    }

    /**
     * Reports opened with ?print=1 leave an audit trail entry. The on-screen
     * [data-print] button is handled client-side, so the query parameter is
     * what marks an intentional printable view.
     */
    private function logPrinted(Request $request, string $report, array $context = []): void
    {
        if (! $request->boolean('print')) {
            return;
        }

        AuditLog::record('printed', 'report', null, null, array_merge([
            'report' => $report,
            'url' => $request->fullUrl(),
        ], $context));
    }
}
