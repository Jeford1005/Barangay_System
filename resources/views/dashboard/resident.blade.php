@extends('layouts.app')

@section('title', 'My Account')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900">My account</h1>
        <p class="mt-1 text-sm text-slate-500">
            Welcome back, {{ $resident->name }}.
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Profile</h2>

            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Full name</dt>
                    <dd class="font-medium text-slate-900">{{ $resident->name }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Email</dt>
                    <dd class="font-medium text-slate-900">{{ $resident->email }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Account status</dt>
                    <dd>
                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Member since</dt>
                    <dd class="font-medium text-slate-900">{{ $resident->created_at->format('M j, Y') }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Resident services</h2>
            <p class="mt-2 text-sm text-slate-500">
                Barangay clearances, certificates and record requests will appear here as they are built.
            </p>
        </div>
    </div>
@endsection
