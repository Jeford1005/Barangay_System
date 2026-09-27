@extends('layouts.app')

@section('title', 'Blotter report')

@section('content')
    @php
        $statusStyles = [
            'Open' => ['border' => 'border-amber-200', 'bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'value' => 'text-amber-900', 'chip' => 'bg-amber-100 text-amber-800'],
            'Pending' => ['border' => 'border-sky-200', 'bg' => 'bg-sky-50', 'text' => 'text-sky-700', 'value' => 'text-sky-900', 'chip' => 'bg-sky-100 text-sky-800'],
            'Resolved' => ['border' => 'border-emerald-200', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'value' => 'text-emerald-900', 'chip' => 'bg-emerald-100 text-emerald-800'],
            'Dismissed' => ['border' => 'border-slate-200', 'bg' => 'bg-slate-50', 'text' => 'text-slate-500', 'value' => 'text-slate-900', 'chip' => 'bg-slate-200 text-slate-700'],
        ];
    @endphp

    <div class="mb-8 flex flex-wrap items-end justify-between gap-4 print:hidden">
        <div>
            <nav class="mb-2 text-xs font-medium text-slate-400" aria-label="Breadcrumb">
                <a href="{{ route('reports.index') }}" class="transition hover:text-slate-700">Reports</a>
                <span class="mx-1">&rsaquo;</span>
                <span class="text-slate-500">Blotter</span>
            </nav>
            <h1 class="text-2xl font-semibold text-slate-900">Blotter report</h1>
            <p class="mt-1 text-sm text-slate-500">
                Case volume by incident type, status and month for the selected period.
            </p>
        </div>

        <button type="button"
                data-print
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
            Print report
        </button>
    </div>

    {{-- ── chips ── --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cases in range</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($totalCases) }}</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Open or pending</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ number_format(($statusTotals['Open'] ?? 0) + ($statusTotals['Pending'] ?? 0)) }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Arrests made</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ number_format($arrests) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Period</p>
            <p class="mt-1 text-sm font-semibold text-slate-900">
                {{ $from ? \Illuminate\Support\Carbon::parse($from)->format('M j, Y') : 'Earliest' }}
                &rarr;
                {{ $to ? \Illuminate\Support\Carbon::parse($to)->format('M j, Y') : 'Today' }}
            </p>
        </div>
    </div>

    @include('reports._range', [
        'action' => route('reports.blotter'),
        'hint' => 'Defaults to the first day of the previous month through today.',
    ])

    {{-- ── status totals ── --}}
    <section class="mb-8">
        <h2 class="mb-3 text-base font-semibold text-slate-900">Status totals</h2>
        <div class="grid gap-4 sm:grid-cols-4">
            @foreach ($statusTotals as $status => $count)
                @php($tone = $statusStyles[$status] ?? $statusStyles['Dismissed'])
                <div class="rounded-xl border {{ $tone['border'] }} {{ $tone['bg'] }} p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide {{ $tone['text'] }}">{{ $status }}</p>
                    <p class="mt-1 text-2xl font-semibold {{ $tone['value'] }}">{{ number_format($count) }}</p>
                    <p class="mt-1 text-xs {{ $tone['text'] }}">
                        {{ $totalCases > 0 ? number_format($count / $totalCases * 100, 1) : '0.0' }}% of cases
                    </p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ── incidents by type ── --}}
    <section class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm print:shadow-none">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-900">Incidents by type</h2>
            <p class="text-sm text-slate-500">Sorted by frequency, with the status breakdown for each type.</p>
        </div>

        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Incident type</th>
                    <th class="px-5 py-3 text-right">Open</th>
                    <th class="px-5 py-3 text-right">Pending</th>
                    <th class="px-5 py-3 text-right">Resolved</th>
                    <th class="px-5 py-3 text-right">Dismissed</th>
                    <th class="px-5 py-3 text-right">Total</th>
                    <th class="px-5 py-3 text-right">Share</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($byType as $line)
                    <tr>
                        <td class="px-5 py-3.5 font-medium text-slate-900">{{ $line['type'] }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-amber-700">{{ number_format($line['Open']) }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-sky-700">{{ number_format($line['Pending']) }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-emerald-700">{{ number_format($line['Resolved']) }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-500">{{ number_format($line['Dismissed']) }}</td>
                        <td class="px-5 py-3.5 text-right font-semibold tabular-nums text-slate-900">{{ number_format($line['total']) }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-500">
                            {{ $totalCases > 0 ? number_format($line['total'] / $totalCases * 100, 1) : '0.0' }}%
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-8 text-center text-sm text-slate-500">
                            No blotter cases were recorded in this period.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{-- ── 12-month trend ── --}}
    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm print:shadow-none">
        <div class="mb-4">
            <h2 class="text-base font-semibold text-slate-900">Monthly trend</h2>
            <p class="text-sm text-slate-500">Cases per month for the last 12 months ending on the last day of the range.</p>
        </div>

        <ul class="space-y-2">
            @foreach ($monthlyTrend as $bucket)
                <li class="flex items-center gap-3">
                    <span class="w-20 shrink-0 text-xs font-medium text-slate-500">{{ $bucket['label'] }}</span>
                    <div class="h-4 flex-1 overflow-hidden rounded bg-slate-100">
                        <div class="h-full rounded bg-sky-500" style="width: {{ $bucket['pct'] }}%"></div>
                    </div>
                    <span class="w-8 shrink-0 text-right text-xs font-semibold tabular-nums text-slate-700">{{ $bucket['count'] }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- ── recent cases ── --}}
    <section class="mt-8 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm print:shadow-none">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-900">Latest 10 cases</h2>
            <p class="text-sm text-slate-500">Most recent entries inside the selected period.</p>
        </div>

        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Case No.</th>
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Incident</th>
                    <th class="px-5 py-3">Location</th>
                    <th class="px-5 py-3">Parties</th>
                    <th class="px-5 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($recent as $case)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-3.5">
                            <a href="{{ route('blotter.print', $case) }}" class="font-medium text-sky-700 hover:underline">
                                {{ $case->case_number }}
                            </a>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3.5 text-slate-700">
                            {{ $case->incident_date?->format('M j, Y') }}
                            @if ($case->incident_time)
                                <span class="text-xs text-slate-400">&middot; {{ $case->incident_time->format('H:i') }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-slate-900">{{ $case->incident_type }}</td>
                        <td class="px-5 py-3.5 text-slate-700">{{ $case->location }}</td>
                        <td class="px-5 py-3.5 text-slate-700">
                            <span class="block">{{ $case->complainant_name }}</span>
                            <span class="block text-xs text-slate-400">vs. {{ $case->respondent_name ?: 'Unknown' }}</span>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3.5">
                            @php($tone = $statusStyles[$case->status] ?? $statusStyles['Dismissed'])
                            <span class="rounded-full {{ $tone['chip'] }} px-2.5 py-0.5 text-xs font-semibold">{{ $case->status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">No cases in this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <p class="mt-6 text-xs text-slate-400 print:hidden">
        Generated {{ now()->format('F j, Y \a\t H:i') }} &middot; Barangay Bidduang Barangay Management System
    </p>
@endsection
