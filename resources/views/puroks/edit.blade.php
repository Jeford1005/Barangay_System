@extends('layouts.app')

@section('title', 'Edit purok')

@section('content')
    <div class="mb-8">
        <a href="{{ route('puroks.index') }}" class="text-sm font-medium text-sky-700 transition hover:text-sky-800">
            &larr; Back to puroks
        </a>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Edit purok</h1>
        <p class="mt-1 text-sm text-slate-500">
            Updating <span class="font-medium text-slate-700">{{ $purok->name }}</span>
            ({{ $purok->residents()->count() }} resident(s), {{ $purok->households()->count() }} household(s)).
        </p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:max-w-xl">
        @include('puroks._form', [
            'action' => route('puroks.update', $purok),
            'method' => 'PUT',
            'purok' => $purok,
            'submitLabel' => 'Save changes',
        ])

        <div class="mt-4 text-center">
            <a href="{{ route('puroks.index') }}" class="text-sm text-slate-500 transition hover:text-slate-800">
                Cancel
            </a>
        </div>
    </div>
@endsection
