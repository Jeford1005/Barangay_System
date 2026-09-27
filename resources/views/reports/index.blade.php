@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4 print:hidden">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Reports</h1>
            <p class="mt-1 text-sm text-slate-500">
                Three official summaries of the barangay record — every figure is computed live from current data.
            </p>
        </div>

        <a href="{{ route('analytics') }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            Open analytics
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <a href="{{ route('reports.population') }}"
           class="group flex flex-col rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-sky-300 hover:shadow">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Demographics</p>
            <h2 class="mt-2 text-lg font-semibold text-slate-900">Population report</h2>
            <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-500">
                Active residents per purok by sex, plus the age-bracket breakdown from child to senior.
            </p>
            <span class="mt-5 text-sm font-medium text-sky-700 transition group-hover:text-sky-900">Open report &rarr;</span>
        </a>

        <a href="{{ route('reports.blotter') }}"
           class="group flex flex-col rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-sky-300 hover:shadow">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Peace and order</p>
            <h2 class="mt-2 text-lg font-semibold text-slate-900">Blotter report</h2>
            <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-500">
                Cases within a date range: incident types, status totals, arrests and a 12-month trend.
            </p>
            <span class="mt-5 text-sm font-medium text-sky-700 transition group-hover:text-sky-900">Open report &rarr;</span>
        </a>

        <a href="{{ route('reports.welfare') }}"
           class="group flex flex-col rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-sky-300 hover:shadow">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Social services</p>
            <h2 class="mt-2 text-lg font-semibold text-slate-900">Welfare report</h2>
            <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-500">
                Assistance requests with requested, approved and released amounts per assistance type.
            </p>
            <span class="mt-5 text-sm font-medium text-sky-700 transition group-hover:text-sky-900">Open report &rarr;</span>
        </a>
    </div>

    <div class="mt-8 rounded-xl border border-sky-200 bg-sky-50 px-5 py-4">
        <p class="text-sm text-sky-900">
            <span class="font-semibold">Live reports.</span>
            Nothing here is cached — each report is recomputed the moment it is opened, so it always reflects the
            latest entries. Use the date pickers on each report to narrow the period, then print it for the record.
        </p>
    </div>
@endsection
