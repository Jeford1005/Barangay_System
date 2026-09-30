<x-app-layout>
@section('page_header')
    <x-page-header title="Something went wrong" subtitle="An unexpected error occurred on our end." />
@endsection

@section('content')
<div class="mx-auto max-w-xl py-14 text-center">
    <p class="text-6xl font-bold tracking-tight text-slate-200" aria-hidden="true">500</p>
    <h2 class="mt-4 text-lg font-semibold text-slate-900">Please try again in a moment</h2>
    <p class="mt-2 text-sm text-slate-500">The error was logged. If it keeps happening, contact the barangay office staff.</p>
    <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="btn btn-primary mt-6">Back to home</a>
</div>
@endsection
</x-app-layout>
