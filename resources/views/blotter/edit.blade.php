@extends('layouts.app')

@section('title', 'Edit ' . $blotter->case_number)

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <nav class="mb-2 text-xs font-medium text-slate-400" aria-label="Breadcrumb">
                <a href="{{ route('blotter.index') }}" class="transition hover:text-slate-700">Blotter</a>
                <span class="mx-1">&rsaquo;</span>
                <span class="text-slate-500">{{ $blotter->case_number }}</span>
            </nav>
            <h1 class="text-2xl font-semibold text-slate-900">Edit case {{ $blotter->case_number }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                Recorded {{ $blotter->created_at->format('M j, Y \a\t H:i') }}
                @if ($blotter->recorder)
                    by {{ $blotter->recorder->name }}
                @endif
                &middot; changes are written to the audit trail.
            </p>
        </div>

        <a href="{{ route('blotter.print', $blotter) }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            View case sheet
        </a>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @include('blotter._form')
    </div>
@endsection
