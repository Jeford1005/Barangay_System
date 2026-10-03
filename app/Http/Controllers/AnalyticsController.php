<?php

namespace App\Http\Controllers;

use App\Models\Blotter;
use App\Models\CertificateIssuance;
use App\Models\CertificateRequest;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Models\Welfare;

class AnalyticsController extends Controller
{
    public function index()
    {
        // ---- Headline KPIs (COUNT queries only — no resident rows hydrated) --
        $activeResidents = Resident::where('status', 'Active');

        $residentCount = (clone $activeResidents)->count();
        $voterCount = (clone $activeResidents)->where('voter_status', true)->count();

        $kpis = (object) [
            'residents' => $residentCount,
            'households' => Household::count(),
            'puroks' => Purok::count(),
            'certificates' => CertificateIssuance::issued()->count(),
            'pendingRequests' => CertificateRequest::pending()->count(),
            'pendingApprovals' => User::where('user_type', 'resident')->where('status', 'pending')->count(),
            'openCases' => Blotter::whereIn('status', ['Open', 'Pending'])->count(),
            'voters' => $voterCount,
        ];

        // ---- Sex distribution (GROUP BY in SQL) ------------------------------
        $sexCounts = (clone $activeResidents)->selectRaw('sex, COUNT(*) as c')->groupBy('sex')->pluck('c', 'sex');
        $sexDistribution = [
            ['label' => 'Male', 'count' => (int) ($sexCounts['Male'] ?? 0), 'color' => '#0284c7'],
            ['label' => 'Female', 'count' => (int) ($sexCounts['Female'] ?? 0), 'color' => '#1e293b'],
            ['label' => 'Other', 'count' => (int) ($sexCounts['Other'] ?? 0), 'color' => '#94a3b8'],
        ];

        // ---- Age brackets (PSA-style, shared with the population report) ----
        // Counted from birth-date thresholds in SQL (same anniversary math
        // the reports use) instead of hydrating every resident.
        $asOf = now();
        $ageBrackets = [
            ['label' => '0–6', 'min' => 0, 'max' => 6, 'color' => '#38bdf8'],
            ['label' => '7–17', 'min' => 7, 'max' => 17, 'color' => '#0284c7'],
            ['label' => '18–30', 'min' => 18, 'max' => 30, 'color' => '#0369a1'],
            ['label' => '31–45', 'min' => 31, 'max' => 45, 'color' => '#059669'],
            ['label' => '46–59', 'min' => 46, 'max' => 59, 'color' => '#d97706'],
            ['label' => '60+', 'min' => 60, 'max' => 200, 'color' => '#7c3aed'],
        ];
        $bracketSelects = [];
        $bracketBindings = [];
        foreach ($ageBrackets as $index => $bracket) {
            $lower = $asOf->copy()->subYears($bracket['max'] + 1)->toDateString();
            if ($bracket['min'] === 0) {
                $bracketSelects[] = "SUM(CASE WHEN birth_date > ? THEN 1 ELSE 0 END) as bracket_{$index}";
                $bracketBindings[] = $lower;
            } else {
                $upper = $asOf->copy()->subYears($bracket['min'])->toDateString();
                $bracketSelects[] = "SUM(CASE WHEN birth_date > ? AND birth_date <= ? THEN 1 ELSE 0 END) as bracket_{$index}";
                $bracketBindings[] = $lower;
                $bracketBindings[] = $upper;
            }
        }
        $ageRow = (clone $activeResidents)->selectRaw(implode(', ', $bracketSelects), $bracketBindings)->first();
        $ageDistribution = collect($ageBrackets)
            ->map(fn (array $bracket, int $index) => [
                'label' => $bracket['label'],
                'count' => (int) ($ageRow?->{'bracket_'.$index} ?? 0),
                'color' => $bracket['color'],
            ])
            ->all();

        // ---- Residents by purok (GROUP BY in SQL) ----------------------------
        $purokCounts = (clone $activeResidents)->selectRaw('purok_id, COUNT(*) as c')->groupBy('purok_id')->get();
        $byPurok = Purok::orderBy('name')
            ->pluck('name', 'id')
            ->map(fn (string $name, int $id) => [
                'label' => $name,
                'count' => (int) ($purokCounts->first(fn ($row) => (int) $row->purok_id === $id)?->c ?? 0),
            ])
            ->values()
            ->all();
        $unassigned = (int) ($purokCounts->first(fn ($row) => $row->purok_id === null)?->c ?? 0);
        if ($unassigned > 0) {
            $byPurok[] = ['label' => 'No Purok', 'count' => $unassigned];
        }
        $maxPurok = max(1, collect($byPurok)->max('count'));

        // ---- Blotter: status split (GROUP BY in SQL) + top complaint types ---
        $blotterCounts = Blotter::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $blotterStatus = [
            ['label' => 'Open', 'count' => (int) ($blotterCounts['Open'] ?? 0), 'color' => '#dc2626'],
            ['label' => 'Pending', 'count' => (int) ($blotterCounts['Pending'] ?? 0), 'color' => '#d97706'],
            ['label' => 'Resolved', 'count' => (int) ($blotterCounts['Resolved'] ?? 0), 'color' => '#10b981'],
            ['label' => 'Dismissed', 'count' => (int) ($blotterCounts['Dismissed'] ?? 0), 'color' => '#64748b'],
        ];

        $topComplaints = Blotter::query()
            ->selectRaw('complaint_type, count(*) as total')
            ->groupBy('complaint_type')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => ['label' => $row->complaint_type, 'count' => $row->total])
            ->all();
        $maxComplaint = max(1, collect($topComplaints)->max('count'));

        // ---- 12-month blotter trend (cases per month) -----------------------
        $blotterTrend = $this->monthlyTrend(Blotter::class, 'complaint_date');

        // ---- 12-month certificate issuance trend ----------------------------
        $certTrend = $this->monthlyTrend(CertificateIssuance::class, 'created_at');

        // ---- Welfare: status split (GROUP BY in SQL) + peso totals ------------
        $welfareCounts = Welfare::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $welfareStatus = [
            ['label' => 'Requested', 'count' => (int) ($welfareCounts['Requested'] ?? 0)],
            ['label' => 'Under Review', 'count' => (int) ($welfareCounts['Under Review'] ?? 0)],
            ['label' => 'Approved', 'count' => (int) ($welfareCounts['Approved'] ?? 0)],
            ['label' => 'Released', 'count' => (int) ($welfareCounts['Released'] ?? 0)],
            ['label' => 'Denied', 'count' => (int) ($welfareCounts['Denied'] ?? 0)],
        ];
        $welfareSums = Welfare::query()->selectRaw(
            "COALESCE(SUM(CASE WHEN status IN ('Approved', 'Released') THEN approved_amount ELSE 0 END), 0) as approved,"
            ." COALESCE(SUM(CASE WHEN status = 'Released' THEN approved_amount ELSE 0 END), 0) as released",
        )->first();
        $welfareAmounts = (object) [
            'approved' => (float) ($welfareSums->approved ?? 0),
            'released' => (float) ($welfareSums->released ?? 0),
        ];

        // ---- Registrations in the last 12 months ----------------------------
        $registrationTrend = $this->monthlyTrend(Resident::class, 'created_at', activeOnly: true);

        // ---- Seniors & voters (planning numbers) ----------------------------
        // The 60+ bracket above already counts birth dates at most 60 years
        // back, which is exactly the senior definition.
        $seniors = $ageDistribution[5]['count'] ?? 0;

        return view('analytics.index', compact(
            'kpis', 'sexDistribution', 'ageDistribution', 'byPurok', 'maxPurok',
            'blotterStatus', 'topComplaints', 'maxComplaint', 'blotterTrend',
            'certTrend', 'welfareStatus', 'welfareAmounts', 'registrationTrend', 'seniors',
        ));
    }

    /**
     * Monthly counts for the last 12 months for a model/date column.
     * Returns labels like "Oct 2025" and counts, zero-filled. Buckets are
     * grouped in SQL by year-month (`%Y-%m`, never month number alone) in
     * one aggregate query instead of hydrating every row and bucketing in
     * PHP; the month expression is driver-aware so the query stays portable
     * across sqlite (dev/tests) and MySQL (production). Grouping by
     * year-month keeps Jan-2025 and Jan-2026 in separate buckets.
     */
    private function monthlyTrend(string $model, string $dateColumn, bool $activeOnly = false): array
    {
        $from = now()->subMonths(11)->startOfMonth();

        $driver = $model::query()->getConnection()->getDriverName();
        $monthExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', {$dateColumn})"
            : "DATE_FORMAT({$dateColumn}, '%Y-%m')";

        $query = $model::where($dateColumn, '>=', $from);
        if ($activeOnly) {
            $query->where('status', 'Active');
        }

        $raw = $query
            ->selectRaw("{$monthExpression} as ym, COUNT(*) as c")
            ->groupBy('ym')
            ->pluck('c', 'ym');

        $out = [];
        $cursor = $from->copy();
        for ($i = 0; $i < 12; $i++) {
            $key = $cursor->format('Y-m');
            $out[] = [
                // Full year on purpose ("Jan 2026", not "Jan 26"): the
                // 12-month window always spans a year boundary, and the
                // chart below renders the whole label so Jan-2025 can never
                // be mistaken for Jan-2026.
                'label' => $cursor->format('M Y'),
                'count' => (int) ($raw[$key] ?? 0),
            ];
            $cursor->addMonth();
        }

        return $out;
    }
}
