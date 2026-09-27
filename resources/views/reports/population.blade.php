@extends('layouts.app')

@section('title', 'Population report')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4 print:hidden">
        <div>
            <nav class="mb-2 text-xs font-medium text-slate-400" aria-label="Breadcrumb">
                <a href="{{ route('reports.index') }}" class="transition hover:text-slate-700">Reports</a>
                <span class="mx-1">&rsaquo;</span>
                <span class="text-slate-500">Population</span>
            </nav>
            <h1 class="text-2xl font-semibold text-slate-900">Population report</h1>
            <p class="mt-1 text-sm text-slate-500">
                Active residents by purok and age bracket, live from the resident roster.
            </p>
        </div>

        <button type="button"
                data-print
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
            Print report
        </button>
    </div>

    {{-- ── chips ── --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Active residents</p>
            <p class="mt-1 text-2xl font-semibold text-sky-900">{{ number_format($totals['total']) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Households</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($households) }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Period</p>
            <p class="mt-1 text-sm font-semibold text-emerald-900">
                @if ($from === null && $to === null)
                    All records to date
                @else
                    {{ $from ? \Illuminate\Support\Carbon::parse($from)->format('M j, Y') : 'Start' }}
                    &rarr;
                    {{ $to ? \Illuminate\Support\Carbon::parse($to)->format('M j, Y') : 'Today' }}
                @endif
            </p>
        </div>
    </div>

    @include('reports._range', [
        'action' => route('reports.population'),
        'hint' => 'Leave both dates blank for the full roster. When a range is set, the report counts residents recorded within it.',
    ])

    {{-- ── residents per purok ── --}}
    <section class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm print:shadow-none">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-900">Residents per purok</h2>
            <p class="text-sm text-slate-500">Active residents only, grouped by sex.</p>
        </div>

        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Purok</th>
                    <th class="px-5 py-3 text-right">Male</th>
                    <th class="px-5 py-3 text-right">Female</th>
                    <th class="px-5 py-3 text-right">Other</th>
                    <th class="px-5 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr @class([
                        'bg-slate-50/60 font-medium' => $row['label'] === 'No Purok Assigned',
                    ])>
                        <td class="px-5 py-3.5 text-slate-900">{{ $row['label'] }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">{{ number_format($row['male']) }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">{{ number_format($row['female']) }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">{{ number_format($row['other']) }}</td>
                        <td class="px-5 py-3.5 text-right font-semibold tabular-nums text-slate-900">{{ number_format($row['total']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">No active residents found.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="border-t border-slate-200 bg-slate-50 text-sm">
                <tr>
                    <td class="px-5 py-3 font-semibold text-slate-900">Grand total</td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums text-slate-900">{{ number_format($totals['male']) }}</td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums text-slate-900">{{ number_format($totals['female']) }}</td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums text-slate-900">{{ number_format($totals['other']) }}</td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums text-slate-900">{{ number_format($totals['total']) }}</td>
                </tr>
            </tfoot>
        </table>
    </section>

    {{-- ── age brackets ── --}}
    <section class="mt-8 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm print:shadow-none">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-900">Age brackets</h2>
            <p class="text-sm text-slate-500">Distribution of the same residents across statutory age groups.</p>
        </div>

        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Age bracket</th>
                    <th class="px-5 py-3 text-right">Residents</th>
                    <th class="px-5 py-3 text-right">Share</th>
                    <th class="px-5 py-3">Distribution</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($brackets as $bracket)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-3.5 font-medium text-slate-900">{{ $bracket['label'] }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">{{ number_format($bracket['count']) }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">{{ number_format($bracket['pct'], 1) }}%</td>
                        <td class="px-5 py-3.5">
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-sky-500" style="width: {{ $bracket['pct'] }}%"></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t border-slate-200 bg-slate-50 text-sm">
                <tr>
                    <td class="px-5 py-3 font-semibold text-slate-900">Total</td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums text-slate-900">{{ number_format($totals['total']) }}</td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums text-slate-900">100.0%</td>
                    <td class="px-5 py-3 text-xs text-slate-500">Computed from the stored age of each resident.</td>
                </tr>
            </tfoot>
        </table>
    </section>

    <p class="mt-6 text-xs text-slate-400 print:hidden">
        Generated {{ now()->format('F j, Y \a\t H:i') }} &middot; Barangay Bidduang Barangay Management System
    </p>
@endsection
