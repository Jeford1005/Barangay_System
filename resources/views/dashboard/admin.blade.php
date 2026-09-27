@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">
            Signed in as {{ auth()->user()->email }} &middot; Administrator
        </p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Total accounts</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $counts['total'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Administrators</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $counts['admin'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Staff &amp; officials</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $counts['staff'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Residents</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $counts['resident'] }}</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <p class="text-sm text-amber-700">Awaiting approval</p>
            <p class="mt-2 text-3xl font-semibold text-amber-900">{{ $counts['pending'] }}</p>
        </div>
    </div>

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
                            <p class="mt-0.5 text-xs text-slate-400">
                                Registered {{ $pending->created_at->diffForHumans() }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('admin.accounts.approve', $pending) }}">
                                @csrf
                                <button type="submit"
                                        class="rounded-lg bg-emerald-600 px-3.5 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-700">
                                    Approve
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.accounts.reject', $pending) }}">
                                @csrf
                                <button type="submit"
                                        class="rounded-lg border border-slate-300 px-3.5 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                                    Reject
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
