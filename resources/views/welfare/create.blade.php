@extends('layouts.app')

@section('title', 'New assistance request')

@section('content')
    <div class="mb-8">
        <a href="{{ route('welfare.index') }}" class="text-sm font-medium text-sky-700 transition hover:text-sky-800">
            &larr; Back to welfare assistance
        </a>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">New assistance request</h1>
        <p class="mt-1 text-sm text-slate-500">
            Take in a resident&rsquo;s request. Approval and release are decided later by an administrator.
        </p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @include('welfare._form')
    </div>
@endsection
