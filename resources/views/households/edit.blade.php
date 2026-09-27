@extends('layouts.app')

@section('title', 'Edit '.$household->household_number)

@section('content')
    <div class="mb-8">
        <a href="{{ route('households.show', $household) }}" class="text-sm font-medium text-sky-700 transition hover:text-sky-800">
            &larr; Back to household
        </a>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Edit {{ $household->household_number }}</h1>
        <p class="mt-1 text-sm text-slate-500">
            Update the address, head of family and household details.
        </p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @include('households._form')
    </div>
@endsection
