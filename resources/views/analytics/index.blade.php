@extends('layouts.app')

@section('title', 'Analytics')

@section('content')
    @php
        $toneMap = [
            'sky' => ['border' => 'border-sky-200', 'bg' => 'bg-sky-50', 'text' => 'text-sky-700', 'value' => 'text-sky-900'],
            'emerald' => ['border' => 'border-emerald-200', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'value' => 'text-emerald-900'],
            'amber' => ['border' => 'border-amber-200', 'bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'value' => 'text-amber-900'],
        ];

        $sexColors = ['Male' => 'bg-sky-500', 'Female' => 'bg-emerald-500', 'Other' => 'bg-amber-400'];

        $statusColors = [
            'Open' => 'bg-amber-400',
            'Pending' => 'bg-sky-500',
            'Resolved' => 'bg-emerald-500',
            'Dismissed' => 'bg-slate-400',
            'Requested' => 'bg-sky-500',
            'Under Review' => 'bg-amber-400',
            'Approved' => 'bg-emerald-500',
            'Denied' => 'bg-red-400',
            'Released' => 'bg-teal-500',
        ];
    @endphp

    <div class="mb-8 flex flex-wrap items-end justify-between gap-4 print:hidden">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Analytics</h1>
            <p class="mt-1 text-sm text-slate-500">
                A live snapshot of the barangay record — roster, cases, welfare and certificate releases.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.index') }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Reports
            </a>
            <button type="button"
                    data-print
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                Print dashboard
            </button>
        </div>
    </div>

    {{-- ── KPI cards ── --}}
    <div class="mb-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($kpis as $kpi)
            @php($tone = $toneMap[$kpi['tone']] ?? $toneMap['sky'])
            <div class="rounded-xl border {{ $tone['border'] }} {{ $tone['bg'] }} p-4">
                <p class="text-xs font-semibold uppercase tracking-wide {{ $tone['text'] }}">{{ $kpi['label'] }}</p>
                <p class="mt-1 text-3xl font-semibold {{ $tone['value'] }}">{{ $kpi['value'] }}</p>
                <p class="mt-1 text-xs {{ $tone['text'] }}">{{ $kpi['hint'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- ── residents by purok ── --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm print:shadow-none">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-slate-900">Residents by purok</h2>
                <p class="text-sm text-slate-500">Active residents, largest purok first by bar length.</p>
            </div>

            <ul class="space-y-3">
                @forelse ($purokBars as $bar)
                    <li>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-slate-700">{{ $bar['label'] }}</span>
                            <span class="tabular-nums text-slate-500">{{ number_format($bar['count']) }}</span>
                        </div>
                        <div class="mt-1.5 h-3 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-sky-500" style="width: {{ $bar['pct'] }}%"></div>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No active residents on the roster.</li>
                @endforelse
            </ul>
        </section>

        {{-- ── sex split ── --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm print:shadow-none">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-slate-900">Sex split</h2>
                <p class="text-sm text-slate-500">{{ number_format($sexTotal) }} active residents in total.</p>
            </div>

            <div class="flex h-6 w-full overflow-hidden rounded-full bg-slate-100">
                @foreach ($sexBars as $segment)
                    <div class="{{ $sexColors[$segment['label']] ?? 'bg-slate-400' }}"
                         style="width: {{ $segment['pct'] }}%"
                         title="{{ $segment['label'] }}: {{ $segment['count'] }}"></div>
                @endforeach
            </div>

            <ul class="mt-4 grid gap-2 sm:grid-cols-3">
                @foreach ($sexBars as $segment)
                    <li class="flex items-center gap-2 text-sm">
                        <span class="h-3 w-3 shrink-0 rounded-full {{ $sexColors[$segment['label']] ?? 'bg-slate-400' }}"></span>
                        <span class="text-slate-600">{{ $segment['label'] }}</span>
                        <span class="ml-auto font-medium tabular-nums text-slate-900">
                            {{ number_format($segment['count']) }}
                            <span class="text-xs font-normal text-slate-400">({{ number_format($segment['pct'], 1) }}%)</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- ── blotter status split ── --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm print:shadow-none">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-slate-900">Blotter status split</h2>
                <p class="text-sm text-slate-500">{{ number_format($blotterTotal) }} cases on file.</p>
            </div>

            <ul class="space-y-3">
                @foreach ($blotterBars as $bar)
                    <li>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-slate-700">{{ $bar['label'] }}</span>
                            <span class="tabular-nums text-slate-500">
                                {{ number_format($bar['count']) }}
                                <span class="text-xs text-slate-400">({{ number_format($bar['share'], 1) }}%)</span>
                            </span>
                        </div>
                        <div class="mt-1.5 h-3 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full {{ $statusColors[$bar['label']] ?? 'bg-slate-400' }}" style="width: {{ $bar['pct'] }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- ── welfare status split ── --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm print:shadow-none">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-slate-900">Welfare status split</h2>
                <p class="text-sm text-slate-500">{{ number_format($welfareTotal) }} assistance requests on file.</p>
            </div>

            <ul class="space-y-3">
                @foreach ($welfareBars as $bar)
                    <li>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-slate-700">{{ $bar['label'] }}</span>
                            <span class="tabular-nums text-slate-500">
                                {{ number_format($bar['count']) }}
                                <span class="text-xs text-slate-400">({{ number_format($bar['share'], 1) }}%)</span>
                            </span>
                        </div>
                        <div class="mt-1.5 h-3 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full {{ $statusColors[$bar['label']] ?? 'bg-slate-400' }}" style="width: {{ $bar['pct'] }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    {{-- ── certificate issuance trend ── --}}
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm print:shadow-none">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Certificate issuances</h2>
                <p class="text-sm text-slate-500">
                    Released per month over the last 12 months
                    @if ($certTrend->isNotEmpty())
                        &middot; {{ $certTrend->first()['label'] }} &rarr; {{ $certTrend->last()['label'] }}
                    @endif
                </p>
            </div>
            <p class="text-sm text-slate-500">
                Peak month: <span class="font-semibold text-slate-900">{{ number_format($certPeakCount) }}</span>
            </p>
        </div>

        <div class="flex h-44 items-end gap-1.5">
            @foreach ($certTrend as $bucket)
                <div class="flex h-full flex-1 flex-col justify-end">
                    <div class="w-full rounded-t bg-emerald-500"
                         style="height: {{ $bucket['count'] > 0 ? max($bucket['pct'], 3) : 0 }}%"
                         title="{{ $bucket['label'] }}: {{ $bucket['count'] }}"></div>
                </div>
            @endforeach
        </div>

        <div class="mt-2 flex gap-1.5 border-t border-slate-200 pt-2">
            @foreach ($certTrend as $bucket)
                <span class="flex-1 text-center text-[10px] uppercase tracking-wide text-slate-400">
                    {{ \Illuminate\Support\Carbon::parse($bucket['label'])->format('M') }}
                </span>
            @endforeach
        </div>

        <div class="mt-1 flex gap-1.5">
            @foreach ($certTrend as $bucket)
                <span class="flex-1 text-center text-[11px] font-medium tabular-nums text-slate-600">
                    {{ $bucket['count'] }}
                </span>
            @endforeach
        </div>
    </section>

    <p class="mt-6 text-xs text-slate-400 print:hidden">
        Generated {{ now()->format('F j, Y \a\t H:i') }} &middot; All figures computed live from current records.
    </p>
@endsection
