<x-app-layout>
@section('page_header')
    <x-page-header title="Page not found" subtitle="The page you're looking for doesn't exist or was moved." />
@endsection

@section('content')
<div class="mx-auto max-w-xl py-14 text-center">
    <p class="text-6xl font-bold tracking-tight text-slate-200" aria-hidden="true">404</p>
    <h2 class="mt-4 text-lg font-semibold text-slate-900">We couldn't find that page</h2>
    <p class="mt-2 text-sm text-slate-500">Check the address for typos, or head back home and navigate from there.</p>
    <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="btn btn-primary mt-6">Back to home</a>
</div>
@endsection
</x-app-layout>
