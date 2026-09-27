@extends('layouts.app')

@section('title', 'New household')

@section('content')
    <div class="mb-8">
        <a href="{{ route('households.index') }}" class="text-sm font-medium text-sky-700 transition hover:text-sky-800">
            &larr; Back to households
        </a>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">New household</h1>
        <p class="mt-1 text-sm text-slate-500">
            Register a family. The household number is generated automatically.
        </p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @include('households._form')
    </div>
@endsection
