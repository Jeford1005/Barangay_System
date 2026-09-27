@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">
            Signed in as {{ auth()->user()->email }} &middot; {{ auth()->user()->isAdmin() ? 'Administrator' : 'Barangay staff' }}
        </p>
    </div>

    {{-- module counts --}}
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('residents.index') }}" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 hover:shadow">
            <p class="text-sm text-slate-500">Active residents</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $counts['residents'] }}</p>
            <p class="mt-1 text-xs text-sky-700 opacity-0 transition group-hover:opacity-100">Open directory &rarr;</p>
        </a>
        <a href="{{ route('households.index') }}" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 hover:shadow">
            <p class="text-sm text-slate-500">Households</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $counts['households'] }}</p>
            <p class="mt-1 text-xs text-sky-700 opacity-0 transition group-hover:opacity-100">Open households &rarr;</p>
        </a>
        <a href="{{ route('puroks.index') }}" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 hover:shadow">
            <p class="text-sm text-slate-500">Puroks</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $counts['puroks'] }}</p>
            <p class="mt-1 text-xs text-sky-700 opacity-0 transition group-hover:opacity-100">Open puroks &rarr;</p>
        </a>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Active officials</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $counts['officials'] }}</p>
        </div>
    </div>

    {{-- admin: account approval queue --}}
    @if (auth()->user()->isAdmin())
        <div class="mt-10 rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Account requests</h2>
                    <p class="text-sm text-slate-500">Residents who registered and are waiting to be let in.</p>
                </div>
                <a href="{{ route('admin.accounts.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-800">
                    Manage all accounts &rarr;
                </a>
            </div>

            @if ($pendingUsers->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-slate-500">No pending account requests.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($pendingUsers as $pending)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                            <div>
                                <p class="text-sm font-medium text-slate-900">{{ $pending->name }}</p>
                                <p class="text-sm text-slate-500">{{ $pending->email }}</p>
                                <p class="mt-0.5 text-xs text-slate-400">Registered {{ $pending->created_at->diffForHumans() }}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('admin.accounts.approve', $pending) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-emerald-600 px-3.5 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-700">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.accounts.reject', $pending) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-slate-300 px-3.5 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50">Reject</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- quick links --}}
    <div class="mt-8 flex flex-wrap gap-3">
        <a href="{{ route('reports.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Reports</a>
        <a href="{{ route('analytics') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Analytics</a>
        @if (auth()->user()->hasPermission('certificates.view'))
            <a href="{{ route('certificates.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Certificates</a>
        @endif
        @if (auth()->user()->hasPermission('blotter.view'))
            <a href="{{ route('blotter.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Blotter</a>
        @endif
    </div>
@endsection
