<x-app-layout>
@section('page_header')
    <x-page-header title="Page expired" subtitle="Your session timed out while the page was open." />
@endsection

@section('content')
<div class="mx-auto max-w-xl py-14 text-center">
    <p class="text-6xl font-bold tracking-tight text-slate-200" aria-hidden="true">419</p>
    <h2 class="mt-4 text-lg font-semibold text-slate-900">Your session expired</h2>
    <p class="mt-2 text-sm text-slate-500">Refresh the previous page and try again — anything you submitted was not saved.</p>
    <div class="mt-6 flex items-center justify-center gap-2">
        <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="btn btn-primary">Back to home</a>
        @guest
            <a href="{{ route('login') }}" class="btn btn-neutral">Sign in again</a>
        @endguest
    </div>
</div>
@endsection
</x-app-layout>
