@extends('layouts.app')

@section('title', 'Welfare report')

@section('content')
    @php
        $statusTones = [
            'Requested' => ['border' => 'border-sky-200', 'bg' => 'bg-sky-50', 'text' => 'text-sky-700', 'value' => 'text-sky-900', 'chip' => 'bg-sky-100 text-sky-800'],
            'Under Review' => ['border' => 'border-amber-200', 'bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'value' => 'text-amber-900', 'chip' => 'bg-amber-100 text-amber-800'],
            'Approved' => ['border' => 'border-emerald-200', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'value' => 'text-emerald-900', 'chip' => 'bg-emerald-100 text-emerald-800'],
            'Denied' => ['border' => 'border-red-200', 'bg' => 'bg-red-50', 'text' => 'text-red-600', 'value' => 'text-red-700', 'chip' => 'bg-red-100 text-red-700'],
            'Released' => ['border' => 'border-teal-200', 'bg' => 'bg-teal-50', 'text' => 'text-teal-700', 'value' => 'text-teal-900', 'chip' => 'bg-teal-100 text-teal-800'],
        ];
    @endphp

    <div class="mb-8 flex flex-wrap items-end justify-between gap-4 print:hidden">
        <div>
            <nav class="mb-2 text-xs font-medium text-slate-400" aria-label="Breadcrumb">
                <a href="{{ route('reports.index') }}" class="transition hover:text-slate-700">Reports</a>
                <span class="mx-1">&rsaquo;</span>
                <span class="text-slate-500">Welfare</span>
            </nav>
            <h1 class="text-2xl font-semibold text-slate-900">Welfare report</h1>
            <p class="mt-1 text-sm text-slate-500">
                Assistance requests, approvals and released amounts for the selected period.
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
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requests in range</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($totalRecords) }}</p>
        </div>
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Requested</p>
            <p class="mt-1 text-2xl font-semibold text-sky-900">&#8369;{{ number_format($requestedTotal, 2) }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Approved</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">&#8369;{{ number_format($approvedTotal, 2) }}</p>
        </div>
        <div class="rounded-xl border border-teal-200 bg-teal-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Released</p>
            <p class="mt-1 text-2xl font-semibold text-teal-900">&#8369;{{ number_format($releasedTotal, 2) }}</p>
        </div>
    </div>

    @include('reports._range', [
        'action' => route('reports.welfare'),
        'hint' => 'Defaults to the first day of the previous month through today. Approved totals use the granted amount when one was set.',
    ])

    {{-- ── status totals ── --}}
    <section class="mb-8">
        <h2 class="mb-3 text-base font-semibold text-slate-900">Requests by status</h2>
        <div class="grid gap-4 sm:grid-cols-5">
            @foreach ($statusTotals as $status => $count)
                @php($tone = $statusTones[$status] ?? $statusTones['Requested'])
                <div class="rounded-xl border {{ $tone['border'] }} {{ $tone['bg'] }} p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide {{ $tone['text'] }}">{{ $status }}</p>
                    <p class="mt-1 text-2xl font-semibold {{ $tone['value'] }}">{{ number_format($count) }}</p>
                    <p class="mt-1 text-xs {{ $tone['text'] }}">
                        {{ $totalRecords > 0 ? number_format($count / $totalRecords * 100, 1) : '0.0' }}% of requests
                    </p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ── by assistance type ── --}}
    <section class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm print:shadow-none">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-900">Assistance by type</h2>
            <p class="text-sm text-slate-500">Requested, approved and released amounts per assistance type.</p>
        </div>

        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Assistance type</th>
                    <th class="px-5 py-3 text-right">Requests</th>
                    <th class="px-5 py-3 text-right">Requested</th>
                    <th class="px-5 py-3 text-right">Approved</th>
                    <th class="px-5 py-3 text-right">Released</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($byType as $line)
                    <tr>
                        <td class="px-5 py-3.5 font-medium text-slate-900">{{ $line['type'] }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">{{ number_format($line['count']) }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">&#8369;{{ number_format($line['requested'], 2) }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">&#8369;{{ number_format($line['approved'], 2) }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">&#8369;{{ number_format($line['released'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">
                            No welfare requests were recorded in this period.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="border-t border-slate-200 bg-slate-50 text-sm">
                <tr>
                    <td class="px-5 py-3 font-semibold text-slate-900">Grand total</td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums text-slate-900">{{ number_format($totalRecords) }}</td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums text-slate-900">&#8369;{{ number_format($requestedTotal, 2) }}</td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums text-slate-900">&#8369;{{ number_format($approvedTotal, 2) }}</td>
                    <td class="px-5 py-3 text-right font-semibold tabular-nums text-slate-900">&#8369;{{ number_format($releasedTotal, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </section>

    <p class="mt-6 text-xs text-slate-400 print:hidden">
        Generated {{ now()->format('F j, Y \a\t H:i') }} &middot; Barangay Bidduang Barangay Management System
    </p>
@endsection
