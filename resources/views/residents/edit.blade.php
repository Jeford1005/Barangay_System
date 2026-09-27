@extends('layouts.app')

@section('title', 'Edit resident')

@section('content')
    <div class="mb-8">
        <a href="{{ route('residents.show', $resident) }}" class="text-sm font-medium text-sky-700 transition hover:text-sky-800">
            &larr; Back to profile
        </a>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Edit resident</h1>
        <p class="mt-1 text-sm text-slate-500">
            Updating the record of
            <span class="font-medium text-slate-700">{{ $resident->full_name }}</span>
            &mdash; the full name and age are recalculated automatically.
        </p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @include('residents._form', [
            'action' => route('residents.update', $resident),
            'method' => 'PUT',
            'resident' => $resident,
            'puroks' => $puroks,
            'households' => $households,
            'submitLabel' => 'Save changes',
        ])
    </div>
@endsection
