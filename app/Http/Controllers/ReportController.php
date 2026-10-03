<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\Welfare;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    /**
     * Population report: residents grouped by purok, with sex and
     * standard age-bracket breakdowns. Printable.
     */
    public function population(Request $request)
    {
        [$from, $to, $asOf] = $this->dateRange($request);

        $brackets = $this->ageBrackets();

        $base = Resident::query()
            ->where('status', 'Active')
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from));

        // Sex and age-bracket aggregates per purok in a single GROUP BY. The
        // screen and print views render counts only, so no resident row is
        // hydrated no matter how large the barangay grows.
        [$bracketSelects, $bracketBindings] = $this->bracketAggregates($brackets, $asOf);

        $aggregates = $base
            ->selectRaw(
                "purok_id, COUNT(*) as total,"
                ." SUM(CASE WHEN sex = 'Male' THEN 1 ELSE 0 END) as male,"
                ." SUM(CASE WHEN sex = 'Female' THEN 1 ELSE 0 END) as female,"
                ." SUM(CASE WHEN sex = 'Other' THEN 1 ELSE 0 END) as other,"
                .implode(',', $bracketSelects),
                $bracketBindings,
            )
            ->groupBy('purok_id')
            ->get()
            ->keyBy(fn ($row) => $row->purok_id === null ? 'unassigned' : 'purok:'.$row->purok_id);

        // Purok rows with sex totals and per-bracket counts.
        $puroks = Purok::orderBy('name')->get(['id', 'name']);

        $rowFromAggregate = function ($aggregate, string $label) use ($brackets) {
            return (object) [
                'label' => $label,
                'male' => (int) ($aggregate->male ?? 0),
                'female' => (int) ($aggregate->female ?? 0),
                'other' => (int) ($aggregate->other ?? 0),
                'total' => (int) ($aggregate->total ?? 0),
                'brackets' => collect($brackets)->mapWithKeys(
                    fn ($bracket, $index) => [$bracket['label'] => (int) ($aggregate?->{'bracket_'.$index} ?? 0)],
                ),
            ];
        };

        $rows = $puroks->map(
            fn (Purok $purok) => $rowFromAggregate($aggregates->get('purok:'.$purok->id), $purok->name),
        );

        // Residents without a purok get their own row — nobody silently dropped.
        $unassigned = $aggregates->get('unassigned');
        if ($unassigned && $unassigned->total > 0) {
            $rows->push($rowFromAggregate($unassigned, 'No Purok Assigned'));
        }

        $totals = (object) [
            'male' => $rows->sum('male'),
            'female' => $rows->sum('female'),
            'other' => $rows->sum('other'),
            'total' => $rows->sum('total'),
            'brackets' => collect($brackets)->mapWithKeys(
                fn ($bracket) => [$bracket['label'] => $rows->sum(fn ($row) => $row->brackets[$bracket['label']])],
            ),
        ];

        // Voters are a standing question for barangay planning.
        $voters = Resident::query()
            ->where('status', 'Active')
            ->where('voter_status', true)
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->count();

        if ($request->boolean('print')) {
            if (! $this->isPrefetch($request)) {
                AuditLog::record(
                    'report.printed',
                    auth()->id(),
                    auth()->user()?->email,
                    $request->ip(),
                    $request->userAgent(),
                    ['report' => 'population', 'from' => $from?->toDateString(), 'to' => $to?->toDateString(), 'total' => $totals->total],
                );
            }

            return view('reports.population-print', [
                'rows' => $rows,
                'totals' => $totals,
                'brackets' => $brackets,
                'voters' => $voters,
                'from' => $from,
                'to' => $to,
                'asOf' => $asOf,
            ]);
        }

        $this->auditScreenView($request, 'population', $from, $to, $totals->total);

        return view('reports.population', compact('rows', 'totals', 'brackets', 'voters', 'from', 'to', 'asOf'));
    }

    /**
     * Blotter summary: case volume by type and status for a period,
     * plus monthly trend. Printable.
     */
    public function blotter(Request $request)
    {
        [$from, $to, $asOf] = $this->dateRange($request, defaultMonths: 1);

        $base = Blotter::query()
            ->when($to, fn ($q) => $q->where('complaint_date', '<=', $to->toDateTimeString()))
            ->when($from, fn ($q) => $q->where('complaint_date', '>=', $from->toDateString()));

        // Status/volume aggregates in one GROUP BY-free aggregate row — the
        // views render grouped counts, never full case rows.
        $statusRow = (clone $base)->selectRaw(
            "COUNT(*) as total,"
            ." SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) as open,"
            ." SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,"
            ." SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved,"
            ." SUM(CASE WHEN status = 'Dismissed' THEN 1 ELSE 0 END) as dismissed,"
            ." SUM(CASE WHEN arrest_made = 'Yes' THEN 1 ELSE 0 END) as arrests",
        )->first();

        $statusTotals = (object) [
            'total' => (int) ($statusRow->total ?? 0),
            'open' => (int) ($statusRow->open ?? 0),
            'pending' => (int) ($statusRow->pending ?? 0),
            'resolved' => (int) ($statusRow->resolved ?? 0),
            'dismissed' => (int) ($statusRow->dismissed ?? 0),
            'arrests' => (int) ($statusRow->arrests ?? 0),
        ];

        // Case volume by complaint type and status, grouped in SQL. Keep
        // complaint-type keys so the views iterate exactly as before.
        $byType = (clone $base)
            ->selectRaw(
                "complaint_type, COUNT(*) as count,"
                ." SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) as open,"
                ." SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,"
                ." SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved,"
                ." SUM(CASE WHEN status = 'Dismissed' THEN 1 ELSE 0 END) as dismissed",
            )
            ->groupBy('complaint_type')
            ->get()
            ->mapWithKeys(fn ($row) => [($row->complaint_type ?? '') => (object) [
                'count' => (int) $row->count,
                'open' => (int) $row->open,
                'pending' => (int) $row->pending,
                'resolved' => (int) $row->resolved,
                'dismissed' => (int) $row->dismissed,
            ]])
            ->sortByDesc(fn ($row) => $row->count); // keep complaint-type keys

        // Monthly trend across the selected period, counted per month in SQL.
        $months = [];
        $cursor = $from?->copy()->startOfMonth();
        if ($cursor === null) {
            $earliest = (clone $base)->min('complaint_date');
            $cursor = $earliest ? Carbon::parse($earliest, 'Asia/Manila')->startOfMonth() : now()->startOfMonth();
        }
        $end = $to?->copy()->endOfMonth() ?? now()->endOfMonth();
        while ($cursor <= $end && count($months) < 24) {
            $months[$cursor->format('M Y')] = 0;
            $cursor->addMonth();
        }

        if ($months !== []) {
            $monthCounts = (clone $base)
                ->selectRaw($this->monthExpression('complaint_date').' as ym, COUNT(*) as c')
                ->groupBy('ym')
                ->pluck('c', 'ym');

            $cursor = $from?->copy()->startOfMonth()
                ?? ($earliest ?? null ? Carbon::parse($earliest, 'Asia/Manila')->startOfMonth() : now()->startOfMonth());
            foreach (array_keys($months) as $label) {
                $months[$label] = (int) ($monthCounts[$cursor->format('Y-m')] ?? 0);
                $cursor->addMonth();
            }
        }

        // Recent cases appendix for context (latest 10 in period).
        // Aggregate-only columns: case number, type, date, status.
        $recent = (clone $base)
            ->select(['id', 'case_number', 'complaint_type', 'complaint_date', 'status'])
            ->orderByDesc('complaint_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($request->boolean('print')) {
            if (! $this->isPrefetch($request)) {
                AuditLog::record(
                    'report.printed',
                    auth()->id(),
                    auth()->user()?->email,
                    $request->ip(),
                    $request->userAgent(),
                    ['report' => 'blotter', 'from' => $from?->toDateString(), 'to' => $to?->toDateString(), 'total' => $statusTotals->total],
                );
            }

            return view('reports.blotter-print', [
                'byType' => $byType,
                'statusTotals' => $statusTotals,
                'months' => $months,
                'recent' => $recent,
                'from' => $from,
                'to' => $to,
                'asOf' => $asOf,
            ]);
        }

        $this->auditScreenView($request, 'blotter', $from, $to, $statusTotals->total);

        return view('reports.blotter', compact('byType', 'statusTotals', 'months', 'recent', 'from', 'to', 'asOf'));
    }

    /**
     * Welfare beneficiary list: assistance released/approved in the
     * period, with totals per program and assistance type. Printable.
     */
    public function welfare(Request $request)
    {
        [$from, $to, $asOf] = $this->dateRange($request, defaultMonths: 1);

        $base = Welfare::query();
        if ($to) {
            $base->where('request_date', '<=', $to->toDateTimeString());
        }
        if ($from) {
            $base->where('request_date', '>=', $from->toDateString());
        }

        // Status and peso totals cover the whole period in single aggregate
        // rows — no welfare record is hydrated for the summary cards.
        $statusRow = (clone $base)->selectRaw(
            "COUNT(*) as total,"
            ." SUM(CASE WHEN status = 'Requested' THEN 1 ELSE 0 END) as requested,"
            ." SUM(CASE WHEN status = 'Under Review' THEN 1 ELSE 0 END) as under_review,"
            ." SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,"
            ." SUM(CASE WHEN status = 'Released' THEN 1 ELSE 0 END) as released,"
            ." SUM(CASE WHEN status = 'Denied' THEN 1 ELSE 0 END) as denied",
        )->first();

        $statusTotals = (object) [
            'total' => (int) ($statusRow->total ?? 0),
            // Keyed by status slug so views never guess abbreviations.
            'requested' => (int) ($statusRow->requested ?? 0),
            'under_review' => (int) ($statusRow->under_review ?? 0),
            'approved' => (int) ($statusRow->approved ?? 0),
            'released' => (int) ($statusRow->released ?? 0),
            'denied' => (int) ($statusRow->denied ?? 0),
        ];

        // Only approved and released rows represent money the office committed
        // to. Summing `approved_amount` across every status would book
        // Requested and Denied rows as disbursed on the printed report, and
        // would disagree with the analytics page, which already filters.
        $amountRow = (clone $base)->selectRaw(
            'COALESCE(SUM(requested_amount), 0) as requested,'
            ." COALESCE(SUM(CASE WHEN status IN ('Approved', 'Released') THEN approved_amount ELSE 0 END), 0) as approved,"
            ." COALESCE(SUM(CASE WHEN status = 'Released' THEN approved_amount ELSE 0 END), 0) as released",
        )->first();

        $amounts = (object) [
            'requested' => (float) ($amountRow->requested ?? 0),
            'approved' => (float) ($amountRow->approved ?? 0),
            'released' => (float) ($amountRow->released ?? 0),
        ];

        $breakdownSelect = 'COUNT(*) as count,'
            ." COALESCE(SUM(CASE WHEN status IN ('Approved', 'Released') THEN approved_amount ELSE 0 END), 0) as amount";

        $byType = (clone $base)
            ->selectRaw('assistance_type, '.$breakdownSelect)
            ->groupBy('assistance_type')
            ->get()
            ->mapWithKeys(fn ($row) => [($row->assistance_type ?? '') => (object) [
                'count' => (int) $row->count,
                'amount' => (float) $row->amount,
            ]])
            ->sortKeys();

        $byProgram = (clone $base)
            ->selectRaw('program_name, '.$breakdownSelect)
            ->groupBy('program_name')
            ->get()
            ->mapWithKeys(fn ($row) => [($row->program_name ?? '') => (object) [
                'count' => (int) $row->count,
                'amount' => (float) $row->amount,
            ]])
            ->sortKeys();

        // Screen/print columns only: the views show beneficiary, type,
        // program, amounts, status, and request date — descriptions, remarks,
        // and timestamps never leave the database.
        $listColumns = ['id', 'beneficiary_id', 'beneficiary_name', 'assistance_type', 'program_name', 'status', 'requested_amount', 'approved_amount', 'request_date'];
        $listQuery = fn () => (clone $base)
            ->select($listColumns)
            ->with(['beneficiary:id,first_name,middle_name,last_name,suffix,purok_id', 'beneficiary.purok:id,name'])
            ->orderBy('request_date');

        if ($request->boolean('print')) {
            if (! $this->isPrefetch($request)) {
                AuditLog::record(
                    'report.printed',
                    auth()->id(),
                    auth()->user()?->email,
                    $request->ip(),
                    $request->userAgent(),
                    ['report' => 'welfare', 'from' => $from?->toDateString(), 'to' => $to?->toDateString(), 'total' => $statusTotals->total],
                );
            }

            // The printed extract lists the period's beneficiaries; cap the
            // rows so an unbounded period cannot exhaust memory. The totals
            // above still cover the whole period, and the sheet says so
            // explicitly instead of silently dropping rows past the cap.
            $printCap = 2000;
            $records = $listQuery()->limit($printCap)->get();
            $totalMatching = (int) ($statusTotals->total ?? 0);

            return view('reports.welfare-print', [
                'records' => $records,
                'statusTotals' => $statusTotals,
                'amounts' => $amounts,
                'byType' => $byType,
                'byProgram' => $byProgram,
                'from' => $from,
                'to' => $to,
                'asOf' => $asOf,
                'printCap' => $printCap,
                'totalMatching' => $totalMatching,
                'printTruncated' => $totalMatching > $records->count(),
            ]);
        }

        $this->auditScreenView($request, 'welfare', $from, $to, $statusTotals->total);

        // The screen list is paginated; the summary cards above already carry
        // the whole-period totals.
        $records = $listQuery()->paginate(50)->withQueryString();

        return view('reports.welfare', compact('records', 'statusTotals', 'amounts', 'byType', 'byProgram', 'from', 'to', 'asOf'));
    }

    /**
     * Parse the shared from/to date-range inputs. Defaults:
     * population = all time; blotter/welfare = last N months.
     */
    private function dateRange(Request $request, int $defaultMonths = 0): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1900-01-01'],
            'to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1900-01-01'],
        ]);

        if (! empty($validated['from']) && ! empty($validated['to']) && $validated['to'] < $validated['from']) {
            throw ValidationException::withMessages([
                'to' => 'The end date must be on or after the start date.',
            ]);
        }

        $from = $request->filled('from') ? Carbon::parse($validated['from'], 'Asia/Manila')->startOfDay() : ($defaultMonths ? now()->subMonths($defaultMonths)->startOfDay() : null);
        $to = $request->filled('to') ? Carbon::parse($validated['to'], 'Asia/Manila')->endOfDay() : ($defaultMonths ? now()->endOfDay() : null);
        $asOf = now();

        return [$from, $to, $asOf];
    }

    /**
     * Standard PSA-style age brackets for barangay reporting.
     */
    private function ageBrackets(): array
    {
        return [
            ['label' => '0–6 (Child)', 'min' => 0, 'max' => 6],
            ['label' => '7–17 (Minor)', 'min' => 7, 'max' => 17],
            ['label' => '18–30 (Youth)', 'min' => 18, 'max' => 30],
            ['label' => '31–45 (Adult)', 'min' => 31, 'max' => 45],
            ['label' => '46–59 (Middle-aged)', 'min' => 46, 'max' => 59],
            ['label' => '60+ (Senior)', 'min' => 60, 'max' => 200],
        ];
    }

    /**
     * Per-bracket COUNT aggregates over `birth_date` for the given brackets.
     * A resident lands in [min, max] exactly when the birth date falls in
     * (asOf - (max+1) years, asOf - min years] — the same anniversary math
     * as a PHP age calculation, evaluated as plain DATE comparisons so it
     * runs on both SQLite and MySQL. The youngest bracket carries no upper
     * bound so future birth dates (clamped to age 0 in PHP) stay inside it.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function bracketAggregates(array $brackets, Carbon $asOf): array
    {
        $selects = [];
        $bindings = [];

        foreach (array_values($brackets) as $index => $bracket) {
            $lower = $asOf->copy()->subYears($bracket['max'] + 1)->toDateString();

            if ($bracket['min'] === 0) {
                $selects[] = "SUM(CASE WHEN birth_date > ? THEN 1 ELSE 0 END) as bracket_{$index}";
                $bindings[] = $lower;
            } else {
                $upper = $asOf->copy()->subYears($bracket['min'])->toDateString();
                $selects[] = "SUM(CASE WHEN birth_date > ? AND birth_date <= ? THEN 1 ELSE 0 END) as bracket_{$index}";
                $bindings[] = $lower;
                $bindings[] = $upper;
            }
        }

        return [$selects, $bindings];
    }

    /**
     * Portable `YYYY-MM` grouping expression for a date column: SQLite uses
     * strftime, MySQL uses DATE_FORMAT.
     */
    private function monthExpression(string $column): string
    {
        $driver = Blotter::query()->getConnection()->getDriverName();

        return $driver === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    /**
     * Screen report views expose PII (beneficiary names, case details, age
     * breakdowns) just like the printed extracts, so they are audited too —
     * with the active filters, so the log shows what slice was viewed.
     */
    private function auditScreenView(Request $request, string $report, ?Carbon $from, ?Carbon $to, int $total): void
    {
        // A speculative prefetch is not a human view: skip the audit but
        // still serve the screen below.
        if ($this->isPrefetch($request)) {
            return;
        }

        AuditLog::record(
            'report.viewed',
            auth()->id(),
            auth()->user()?->email,
            $request->ip(),
            $request->userAgent(),
            ['report' => $report, 'from' => $from?->toDateString(), 'to' => $to?->toDateString(), 'total' => $total],
        );
    }

    /**
     * True when the request is a speculative prefetch rather than a human
     * navigation: Chromium sends `Sec-Purpose: prefetch` (older builds
     * `Purpose: prefetch`) and Firefox sends `X-Moz: prefetch`.
     */
    private function isPrefetch(Request $request): bool
    {
        foreach (['Sec-Purpose', 'Purpose', 'X-Moz'] as $header) {
            $value = (string) $request->header($header, '');

            if ($value !== '' && stripos($value, 'prefetch') !== false) {
                return true;
            }
        }

        return false;
    }
}
