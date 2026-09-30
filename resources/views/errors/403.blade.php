<x-app-layout>
@section('page_header')
    <x-page-header title="Access denied" subtitle="You don't have permission to open that page." />
@endsection

@section('content')
<div class="mx-auto max-w-xl py-14 text-center">
    <p class="text-6xl font-bold tracking-tight text-slate-200" aria-hidden="true">403</p>
    <h2 class="mt-4 text-lg font-semibold text-slate-900">This area is restricted</h2>
    <p class="mt-2 text-sm text-slate-500">If you need access, ask an administrator to update your role permissions.</p>
    <a href="{{ url('/') }}" class="btn btn-primary mt-6">Back to home</a>
</div>
@endsection
</x-app-layout>
