@extends('layouts.app')

@section('title', 'New resident')

@section('content')
    <div class="mb-8">
        <a href="{{ route('residents.index') }}" class="text-sm font-medium text-sky-700 transition hover:text-sky-800">
            &larr; Back to residents
        </a>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">New resident</h1>
        <p class="mt-1 text-sm text-slate-500">
            Add a resident to the registry of Barangay Bidduang. Fields marked with <span class="text-red-500">*</span> are required.
        </p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @include('residents._form', [
            'action' => route('residents.store'),
            'method' => 'POST',
            'resident' => $resident,
            'puroks' => $puroks,
            'households' => $households,
            'submitLabel' => 'Create resident',
        ])
    </div>
@endsection
