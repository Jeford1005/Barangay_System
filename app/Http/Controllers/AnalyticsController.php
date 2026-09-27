<?php

namespace App\Http\Controllers;

use App\Models\Blotter;
use App\Models\CertificateIssuance;
use App\Models\CertificateRequest;
use App\Models\Household;
use App\Models\Official;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\Welfare;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Single-screen analytics overview: headline KPIs plus CSS-only charts
 * (Tailwind divs with style="width/height: x%" attributes — no JS charts).
 */
class AnalyticsController extends Controller
{
    public function __invoke(): View
    {
        /* ---------------------------------- KPIs --------------------------------- */

        $activeResidents = Resident::active()->count();

        $kpis = [
            ['label' => 'Active residents', 'value' => number_format($activeResidents), 'hint' => 'Barangay roster', 'tone' => 'sky'],
            ['label' => 'Households', 'value' => number_format(Household::count()), 'hint' => 'Registered families', 'tone' => 'sky'],
            ['label' => 'Puroks', 'value' => number_format(Purok::count()), 'hint' => 'Sectors', 'tone' => 'sky'],
            ['label' => 'Officials', 'value' => number_format(Official::where('status', Official::STATUS_ACTIVE)->count()), 'hint' => 'Active barangay officials', 'tone' => 'emerald'],
            ['label' => 'Issued certificates', 'value' => number_format(CertificateIssuance::where('status', CertificateIssuance::STATUS_ISSUED)->count()), 'hint' => 'All-time releases', 'tone' => 'emerald'],
            ['label' => 'Pending certificate requests', 'value' => number_format(CertificateRequest::where('status', CertificateRequest::STATUS_PENDING)->count()), 'hint' => 'Awaiting review', 'tone' => 'amber'],
            ['label' => 'Open blotter cases', 'value' => number_format(Blotter::whereIn('status', ['Open', 'Pending'])->count()), 'hint' => 'Open + pending', 'tone' => 'amber'],
            ['label' => 'Welfare requests pending', 'value' => number_format(Welfare::whereIn('status', ['Requested', 'Under Review'])->count()), 'hint' => 'Requested + under review', 'tone' => 'amber'],
        ];

        /* ------------------------------ Residents by purok ------------------------ */

        $byPurok = Resident::active()
            ->selectRaw('purok_id, count(*) as total')
            ->groupBy('purok_id')
            ->pluck('total', 'purok_id');

        $purokBars = Purok::orderBy('code')
            ->get()
            ->map(fn (Purok $purok): array => [
                'label' => $purok->label(),
                'count' => (int) ($byPurok[$purok->id] ?? 0),
            ])
            ->values()
            ->push(['label' => 'No Purok Assigned', 'count' => (int) ($byPurok[''] ?? $byPurok[null] ?? 0)])
            ->all();

        $purokPeak = max(1, max(array_column($purokBars, 'count')));
        foreach ($purokBars as $index => $bar) {
            $purokBars[$index]['pct'] = round($bar['count'] / $purokPeak * 100, 1);
        }

        /* -------------------------------- Sex split ------------------------------ */

        $sexTotals = Resident::active()
            ->selectRaw('sex, count(*) as total')
            ->groupBy('sex')
            ->pluck('total', 'sex');

        $sexTotal = max(0, (int) $sexTotals->sum());
        $sexBars = collect(Resident::SEXES)
            ->map(fn (string $sex): array => [
                'label' => $sex,
                'count' => (int) ($sexTotals[$sex] ?? 0),
                'pct' => $sexTotal > 0 ? round((int) ($sexTotals[$sex] ?? 0) / $sexTotal * 100, 1) : 0.0,
            ])
            ->all();

        /* --------------------------- Blotter / welfare splits --------------------- */

        $blotterCounts = Blotter::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $blotterTotal = (int) $blotterCounts->sum();
        $blotterPeak = max(1, (int) $blotterCounts->max());

        $blotterBars = collect(Blotter::STATUSES)
            ->map(function (string $status) use ($blotterCounts, $blotterTotal, $blotterPeak): array {
                $count = (int) ($blotterCounts[$status] ?? 0);

                return [
                    'label' => $status,
                    'count' => $count,
                    'pct' => round($count / $blotterPeak * 100, 1),
                    'share' => $blotterTotal > 0 ? round($count / $blotterTotal * 100, 1) : 0.0,
                ];
            })
            ->all();

        $welfareCounts = Welfare::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $welfareTotal = (int) $welfareCounts->sum();
        $welfarePeak = max(1, (int) $welfareCounts->max());

        $welfareBars = collect(Welfare::STATUSES)
            ->map(function (string $status) use ($welfareCounts, $welfareTotal, $welfarePeak): array {
                $count = (int) ($welfareCounts[$status] ?? 0);

                return [
                    'label' => $status,
                    'count' => $count,
                    'pct' => round($count / $welfarePeak * 100, 1),
                    'share' => $welfareTotal > 0 ? round($count / $welfareTotal * 100, 1) : 0.0,
                ];
            })
            ->all();

        /* ------------------- 12-month certificate issuance trend ------------------ */

        $since = now()->startOfMonth()->subMonths(11);
        $issued = CertificateIssuance::where('status', CertificateIssuance::STATUS_ISSUED)
            ->where('issued_at', '>=', $since)
            ->pluck('issued_at');

        $buckets = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = $since->copy()->addMonths($i);
            $buckets[$month->format('Y-m')] = ['label' => $month->format('M Y'), 'count' => 0];
        }
        foreach ($issued as $issuedAt) {
            $key = Carbon::instance($issuedAt)->format('Y-m');
            if (isset($buckets[$key])) {
                $buckets[$key]['count']++;
            }
        }

        $certPeak = max(1, max(array_column($buckets, 'count')));
        $certTrend = collect($buckets)
            ->map(fn (array $bucket): array => $bucket + [
                'pct' => round($bucket['count'] / $certPeak * 100, 1),
            ])
            ->values();

        return view('analytics.index', [
            'kpis' => $kpis,
            'purokBars' => $purokBars,
            'sexBars' => $sexBars,
            'sexTotal' => $sexTotal,
            'blotterBars' => $blotterBars,
            'blotterTotal' => $blotterTotal,
            'welfareBars' => $welfareBars,
            'welfareTotal' => $welfareTotal,
            'certTrend' => $certTrend,
            'certPeakCount' => max(array_column($buckets, 'count')),
        ]);
    }
}
