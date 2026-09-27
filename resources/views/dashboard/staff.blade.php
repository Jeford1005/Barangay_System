@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">
            Signed in as {{ auth()->user()->email }} &middot; Barangay staff
        </p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Residents</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">&mdash;</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Households</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">&mdash;</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Puroks</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">&mdash;</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Certificates issued</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">&mdash;</p>
        </div>
    </div>

    <div class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">
            Barangay modules (residents, households, certificates, blotter) appear here as they are built.
        </p>
    </div>
@endsection
