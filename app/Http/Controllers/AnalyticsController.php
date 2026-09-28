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
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    public function index()
    {
        // ---- Headline KPIs -------------------------------------------------
        $residents = Resident::where('status', 'Active')->get(['id', 'sex', 'birth_date', 'purok_id', 'voter_status']);

        $kpis = (object) [
            'residents' => $residents->count(),
            'households' => Household::count(),
            'puroks' => Purok::count(),
            'certificates' => CertificateIssuance::issued()->count(),
            'pendingRequests' => CertificateRequest::pending()->count(),
            'pendingApprovals' => User::where('user_type', 'resident')->where('status', 'pending')->count(),
            'openCases' => Blotter::whereIn('status', ['Open', 'Pending'])->count(),
            'voters' => $residents->where('voter_status', true)->count(),
        ];

        // ---- Sex distribution ----------------------------------------------
        $sexDistribution = [
            ['label' => 'Male', 'count' => $residents->where('sex', 'Male')->count(), 'color' => '#0284c7'],
            ['label' => 'Female', 'count' => $residents->where('sex', 'Female')->count(), 'color' => '#1e293b'],
            ['label' => 'Other', 'count' => $residents->where('sex', 'Other')->count(), 'color' => '#94a3b8'],
        ];

        // ---- Age brackets (PSA-style, shared with the population report) ----
        $ageBrackets = [
            ['label' => '0–6', 'min' => 0, 'max' => 6, 'color' => '#0284c7'],
            ['label' => '7–17', 'min' => 7, 'max' => 17, 'color' => '#0284c7'],
            ['label' => '18–30', 'min' => 18, 'max' => 30, 'color' => '#0284c7'],
            ['label' => '31–45', 'min' => 31, 'max' => 45, 'color' => '#0284c7'],
            ['label' => '46–59', 'min' => 46, 'max' => 59, 'color' => '#0284c7'],
            ['label' => '60+', 'min' => 60, 'max' => 200, 'color' => '#0284c7'],
        ];
        // Calculate each age once; Carbon age calculations are comparatively
        // expensive when repeated for every bracket on a large resident set.
        $asOf = now();
        $residentAges = $residents->mapWithKeys(
            fn (Resident $resident) => [$resident->id => $this->ageOn($resident->birth_date, $asOf)],
        );
        $ageDistribution = collect($ageBrackets)
            ->map(fn (array $bracket) => [
                'label' => $bracket['label'],
                'count' => $residentAges->filter(
                    fn (?int $age) => $age !== null && $age >= $bracket['min'] && $age <= $bracket['max'],
                )->count(),
                'color' => $bracket['color'],
            ])
            ->all();

        // ---- Residents by purok --------------------------------------------
        $byPurok = Purok::orderBy('name')
            ->get()
            ->map(fn (Purok $purok) => [
                'label' => $purok->name,
                'count' => $residents->where('purok_id', $purok->id)->count(),
            ])
            ->values()
            ->all();
        $unassigned = $residents->whereNull('purok_id')->count();
        if ($unassigned > 0) {
            $byPurok[] = ['label' => 'No Purok', 'count' => $unassigned];
        }
        $maxPurok = max(1, collect($byPurok)->max('count'));

        // ---- Blotter: status split + top complaint types --------------------
        $blotterStatus = [
            ['label' => 'Open', 'count' => Blotter::where('status', 'Open')->count(), 'color' => '#dc2626'],
            ['label' => 'Pending', 'count' => Blotter::where('status', 'Pending')->count(), 'color' => '#d97706'],
            ['label' => 'Resolved', 'count' => Blotter::where('status', 'Resolved')->count(), 'color' => '#10b981'],
            ['label' => 'Dismissed', 'count' => Blotter::where('status', 'Dismissed')->count(), 'color' => '#64748b'],
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

        // ---- Welfare: status split + peso totals ----------------------------
        $welfareStatus = [
            ['label' => 'Requested', 'count' => Welfare::where('status', 'Requested')->count()],
            ['label' => 'Under Review', 'count' => Welfare::where('status', 'Under Review')->count()],
            ['label' => 'Approved', 'count' => Welfare::where('status', 'Approved')->count()],
            ['label' => 'Released', 'count' => Welfare::where('status', 'Released')->count()],
            ['label' => 'Denied', 'count' => Welfare::where('status', 'Denied')->count()],
        ];
        $welfareAmounts = (object) [
            'approved' => (float) Welfare::whereIn('status', ['Approved', 'Released'])->sum('approved_amount'),
            'released' => (float) Welfare::where('status', 'Released')->sum('approved_amount'),
        ];

        // ---- Registrations in the last 12 months ----------------------------
        $registrationTrend = $this->monthlyTrend(Resident::class, 'created_at', activeOnly: true);

        // ---- Seniors & voters (planning numbers) ----------------------------
        $seniors = $residentAges->filter(fn (?int $age) => $age !== null && $age >= 60)->count();

        return view('analytics.index', compact(
            'kpis', 'sexDistribution', 'ageDistribution', 'byPurok', 'maxPurok',
            'blotterStatus', 'topComplaints', 'maxComplaint', 'blotterTrend',
            'certTrend', 'welfareStatus', 'welfareAmounts', 'registrationTrend', 'seniors',
        ));
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

    /**
     * Monthly counts for the last 12 months for a model/date column.
     * Returns labels like "Oct 25" and counts, zero-filled.
     */
    private function monthlyTrend(string $model, string $dateColumn, bool $activeOnly = false): array
    {
        $from = now()->subMonths(11)->startOfMonth();

        $query = $model::where($dateColumn, '>=', $from);
        if ($activeOnly) {
            $query->where('status', 'Active');
        }

        // Bucket on the client side so the query stays portable across
        // sqlite (dev/tests) and MySQL (production) alike.
        $raw = $query
            ->get([$dateColumn])
            ->countBy(fn ($row) => $row->{$dateColumn}->format('Y-m'));

        $out = [];
        $cursor = $from->copy();
        for ($i = 0; $i < 12; $i++) {
            $key = $cursor->format('Y-m');
            $out[] = [
                'label' => $cursor->format('M y'),
                'count' => (int) ($raw[$key] ?? 0),
            ];
            $cursor->addMonth();
        }

        return $out;
    }
}
