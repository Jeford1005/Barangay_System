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

        $residents = Resident::query()
            ->where('status', 'Active')
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->get();

        // Calculate ages once instead of once per bracket and per resident.
        $residentAges = $residents->mapWithKeys(
            fn (Resident $resident) => [$resident->id => $this->ageOn($resident->birth_date, $asOf)],
        );

        // Purok rows with sex totals and per-bracket counts.
        $puroks = Purok::orderBy('name')->get();
        $brackets = $this->ageBrackets();

        $rows = $puroks->map(function (Purok $purok) use ($residents, $residentAges, $brackets) {
            $inPurok = $residents->where('purok_id', $purok->id);

            return (object) [
                'label' => $purok->name,
                'male' => $inPurok->where('sex', 'Male')->count(),
                'female' => $inPurok->where('sex', 'Female')->count(),
                'other' => $inPurok->where('sex', 'Other')->count(),
                'total' => $inPurok->count(),
                'brackets' => collect($brackets)->mapWithKeys(
                    fn ($bracket) => [$bracket['label'] => $inPurok->filter(
                        fn ($r) => $this->ageInBracket($residentAges->get($r->id), $bracket),
                    )->count()],
                ),
            ];
        });

        // Residents without a purok get their own row — nobody silently dropped.
        $unassigned = $residents->whereNull('purok_id');
        if ($unassigned->isNotEmpty()) {
            $rows->push((object) [
                'label' => 'No Purok Assigned',
                'male' => $unassigned->where('sex', 'Male')->count(),
                'female' => $unassigned->where('sex', 'Female')->count(),
                'other' => $unassigned->where('sex', 'Other')->count(),
                'total' => $unassigned->count(),
                'brackets' => collect($brackets)->mapWithKeys(
                    fn ($bracket) => [$bracket['label'] => $unassigned->filter(
                        fn ($r) => $this->ageInBracket($residentAges->get($r->id), $bracket),
                    )->count()],
                ),
            ]);
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
        $voters = $residents->where('voter_status', true)->count();

        if ($request->boolean('print')) {
            AuditLog::record(
                'report.printed',
                auth()->id(),
                auth()->user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['report' => 'population', 'from' => $from?->toDateString(), 'to' => $to?->toDateString(), 'total' => $totals->total],
            );

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

        return view('reports.population', compact('rows', 'totals', 'brackets', 'voters', 'from', 'to', 'asOf'));
    }

    /**
     * Blotter summary: case volume by type and status for a period,
     * plus monthly trend. Printable.
     */
    public function blotter(Request $request)
    {
        [$from, $to, $asOf] = $this->dateRange($request, defaultMonths: 1);

        $cases = Blotter::query()
            ->when($to, fn ($q) => $q->where('complaint_date', '<=', $to->toDateTimeString()))
            ->when($from, fn ($q) => $q->where('complaint_date', '>=', $from->toDateString()))
            ->get();

        $byType = $cases->groupBy('complaint_type')
            ->map(fn ($group) => (object) [
                'count' => $group->count(),
                'open' => $group->where('status', 'Open')->count(),
                'pending' => $group->where('status', 'Pending')->count(),
                'resolved' => $group->where('status', 'Resolved')->count(),
                'dismissed' => $group->where('status', 'Dismissed')->count(),
            ])
            ->sortByDesc(fn ($row) => $row->count); // keep complaint-type keys

        $statusTotals = (object) [
            'total' => $cases->count(),
            'open' => $cases->where('status', 'Open')->count(),
            'pending' => $cases->where('status', 'Pending')->count(),
            'resolved' => $cases->where('status', 'Resolved')->count(),
            'dismissed' => $cases->where('status', 'Dismissed')->count(),
            'arrests' => $cases->where('arrest_made', 'Yes')->count(),
        ];

        // Monthly trend across the selected period.
        $months = [];
        $cursor = $from?->copy()->startOfMonth() ?? $cases->min('complaint_date')?->copy()->startOfMonth() ?? now()->startOfMonth();
        $end = $to?->copy()->endOfMonth() ?? now()->endOfMonth();
        while ($cursor <= $end && count($months) < 24) {
            $months[$cursor->format('M Y')] = 0;
            $cursor->addMonth();
        }
        foreach ($cases as $case) {
            $key = $case->complaint_date->format('M Y');
            if (array_key_exists($key, $months)) {
                $months[$key]++;
            }
        }

        // Recent cases appendix for context (latest 10 in period).
        $recent = $cases->sortByDesc('complaint_date')->take(10);

        if ($request->boolean('print')) {
            AuditLog::record(
                'report.printed',
                auth()->id(),
                auth()->user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['report' => 'blotter', 'from' => $from?->toDateString(), 'to' => $to?->toDateString(), 'total' => $statusTotals->total],
            );

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

        return view('reports.blotter', compact('byType', 'statusTotals', 'months', 'recent', 'from', 'to', 'asOf'));
    }

    /**
     * Welfare beneficiary list: assistance released/approved in the
     * period, with totals per program and assistance type. Printable.
     */
    public function welfare(Request $request)
    {
        [$from, $to, $asOf] = $this->dateRange($request, defaultMonths: 1);

        $query = Welfare::query()->with('beneficiary.purok');

        if ($to) {
            $query->where('request_date', '<=', $to->toDateTimeString());
        }
        if ($from) {
            $query->where('request_date', '>=', $from->toDateString());
        }

        $records = $query->orderBy('request_date')->get();

        $statusTotals = (object) [
            'total' => $records->count(),
            'requested' => $records->where('status', 'Requested')->count(),
            'review' => $records->where('status', 'Under Review')->count(),
            'approved' => $records->where('status', 'Approved')->count(),
            'released' => $records->where('status', 'Released')->count(),
            'denied' => $records->where('status', 'Denied')->count(),
        ];

        // Only approved and released rows represent money the office committed
        // to. Summing `approved_amount` across every status would book
        // Requested and Denied rows as disbursed on the printed report, and
        // would disagree with the analytics page, which already filters.
        $committed = $records->whereIn('status', ['Approved', 'Released']);

        $amounts = (object) [
            'requested' => (float) $records->sum('requested_amount'),
            'approved' => (float) $committed->sum('approved_amount'),
            'released' => (float) $records->where('status', 'Released')->sum('approved_amount'),
        ];

        $byType = $records->groupBy('assistance_type')
            ->map(fn ($group) => (object) [
                'count' => $group->count(),
                'amount' => (float) $group->whereIn('status', ['Approved', 'Released'])->sum('approved_amount'),
            ])
            ->sortKeys();

        $byProgram = $records->groupBy('program_name')
            ->map(fn ($group) => (object) [
                'count' => $group->count(),
                'amount' => (float) $group->whereIn('status', ['Approved', 'Released'])->sum('approved_amount'),
            ])
            ->sortKeys();

        if ($request->boolean('print')) {
            AuditLog::record(
                'report.printed',
                auth()->id(),
                auth()->user()?->email,
                $request->ip(),
                $request->userAgent(),
                ['report' => 'welfare', 'from' => $from?->toDateString(), 'to' => $to?->toDateString(), 'total' => $statusTotals->total],
            );

            return view('reports.welfare-print', [
                'records' => $records,
                'statusTotals' => $statusTotals,
                'amounts' => $amounts,
                'byType' => $byType,
                'byProgram' => $byProgram,
                'from' => $from,
                'to' => $to,
                'asOf' => $asOf,
            ]);
        }

        return view('reports.welfare', compact('records', 'statusTotals', 'amounts', 'byType', 'byProgram', 'from', 'to', 'asOf'));
    }

    /**
     * Parse the shared from/to date-range inputs. Defaults:
     * population = all time; blotter/welfare = last N months.
     */
    private function dateRange(Request $request, int $defaultMonths = 0): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        if (! empty($validated['from']) && ! empty($validated['to']) && $validated['to'] < $validated['from']) {
            throw ValidationException::withMessages([
                'to' => 'The end date must be on or after the start date.',
            ]);
        }

        $from = $request->filled('from') ? Carbon::parse($validated['from'])->startOfDay() : ($defaultMonths ? now()->subMonths($defaultMonths)->startOfDay() : null);
        $to = $request->filled('to') ? Carbon::parse($validated['to'])->endOfDay() : ($defaultMonths ? now()->endOfDay() : null);
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

    private function ageOn(?Carbon $birthDate, Carbon $asOf): ?int
    {
        if ($birthDate === null) {
            return null;
        }

        $age = $asOf->year - $birthDate->year;
        if ($birthDate->month > $asOf->month
            || ($birthDate->month === $asOf->month && $birthDate->day > $asOf->day)) {
            $age--;
        }

        return max(0, $age);
    }

    private function ageInBracket(?int $age, array $bracket): bool
    {
        return $age !== null && $age >= $bracket['min'] && $age <= $bracket['max'];
    }
}
