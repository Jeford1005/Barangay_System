@extends('layouts.app')

@section('title', 'New blotter entry')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <nav class="mb-2 text-xs font-medium text-slate-400" aria-label="Breadcrumb">
                <a href="{{ route('blotter.index') }}" class="transition hover:text-slate-700">Blotter</a>
                <span class="mx-1">&rsaquo;</span>
                <span class="text-slate-500">New entry</span>
            </nav>
            <h1 class="text-2xl font-semibold text-slate-900">New blotter entry</h1>
            <p class="mt-1 text-sm text-slate-500">
                The case number (BLTR-YYYY-NNNN) is assigned automatically when the entry is recorded.
            </p>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @include('blotter._form')
    </div>
@endsection
