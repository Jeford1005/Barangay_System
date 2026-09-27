@extends('layouts.app')

@section('title', $resident->full_name)

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('residents.index') }}" class="text-sm font-medium text-sky-700 transition hover:text-sky-800">
                &larr; Back to residents
            </a>
            <h1 class="mt-2 flex flex-wrap items-center gap-3 text-2xl font-semibold text-slate-900">
                {{ $resident->full_name }}
                @if ($resident->isArchived())
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Archived</span>
                @else
                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                @endif
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $resident->age }} yrs &middot; {{ $resident->sex }} &middot; {{ $resident->civil_status }}
                @if ($resident->purok)
                    &middot; {{ $resident->purok->name }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if (auth()->user()->hasPermission('residents.manage'))
                <a href="{{ route('residents.edit', $resident) }}"
                   class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                    Edit
                </a>
            @endif

            @if (auth()->user()->isAdmin())
                @if ($resident->isArchived())
                    <form method="POST" action="{{ route('residents.restore', $resident) }}">
                        @csrf
                        <button type="submit"
                                class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-sky-700">
                            Restore
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('residents.archive', $resident) }}">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50">
                            Archive
                        </button>
                    </form>
                @endif
            @endif

            <a href="{{ route('residents.index') }}"
               class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Back
            </a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- ── photo + quick facts ── --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            @if ($resident->photo_path)
                <img src="{{ route('residents.photo', $resident) }}"
                     alt="Photo of {{ $resident->full_name }}"
                     class="mx-auto h-44 w-44 rounded-xl object-cover ring-1 ring-slate-200">
            @else
                <div class="mx-auto flex h-44 w-44 items-center justify-center rounded-xl bg-slate-100 text-4xl font-semibold uppercase text-slate-400 ring-1 ring-slate-200">
                    {{ strtoupper(substr($resident->first_name, 0, 1).substr($resident->last_name, 0, 1)) }}
                </div>
            @endif

            <div class="mt-5 text-center">
                <p class="text-base font-semibold text-slate-900">{{ $resident->full_name }}</p>
                <p class="mt-0.5 text-sm text-slate-500">
                    {{ $resident->sex }} &middot; {{ $resident->age }} yrs old
                </p>
            </div>

            <dl class="mt-5 space-y-3 border-t border-slate-100 pt-4 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Civil status</dt>
                    <dd class="font-medium text-slate-800">{{ $resident->civil_status }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Purok</dt>
                    <dd class="font-medium text-slate-800">{{ $resident->purok?->label() ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Household</dt>
                    <dd class="font-medium text-slate-800">{{ $resident->household?->household_number ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Registered</dt>
                    <dd class="font-medium text-slate-800">{{ $resident->created_at?->format('M j, Y') }}</dd>
                </div>
            </dl>
        </div>

        <div class="space-y-6 lg:col-span-2">
            {{-- ── personal details ── --}}
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Personal details</h2>
                </div>

                <dl class="grid gap-x-6 gap-y-4 px-5 py-5 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Full name</dt>
                        <dd class="mt-1 text-slate-800">{{ $resident->full_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date of birth</dt>
                        <dd class="mt-1 text-slate-800">{{ $resident->birth_date?->format('F j, Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Age</dt>
                        <dd class="mt-1 text-slate-800">{{ $resident->age }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sex</dt>
                        <dd class="mt-1 text-slate-800">{{ $resident->sex }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Civil status</dt>
                        <dd class="mt-1 text-slate-800">{{ $resident->civil_status }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Occupation</dt>
                        <dd class="mt-1 text-slate-800">{{ $resident->occupation ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- ── contact & residence ── --}}
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Contact &amp; residence</h2>
                </div>

                <dl class="grid gap-x-6 gap-y-4 px-5 py-5 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</dt>
                        <dd class="mt-1 text-slate-800">{{ $resident->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt>
                        <dd class="mt-1 text-slate-800">{{ $resident->email ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Address</dt>
                        <dd class="mt-1 text-slate-800">
                            {{ $resident->resolvedAddress() ?: '—' }}
                            @if (trim((string) $resident->address) === '' && $resident->household)
                                <span class="text-slate-400">(from household record)</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Purok</dt>
                        <dd class="mt-1 text-slate-800">{{ $resident->purok?->label() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Household</dt>
                        <dd class="mt-1 text-slate-800">
                            @if ($resident->household)
                                {{ $resident->household->household_number }} &mdash; {{ $resident->household->address }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- ── linked portal account ── --}}
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Portal account</h2>
                </div>

                <div class="px-5 py-5 text-sm">
                    @if ($account)
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ $account->email }}</p>
                                <p class="mt-0.5 text-slate-500">Role: {{ ucfirst($account->role) }}</p>
                            </div>

                            @switch($account->status)
                                @case('active')
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                                    @break
                                @case('pending')
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Pending</span>
                                    @break
                                @case('suspended')
                                    <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">Suspended</span>
                                    @break
                                @default
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">{{ ucfirst($account->status) }}</span>
                            @endswitch
                        </div>
                    @else
                        <p class="text-slate-500">No portal account is linked to this record.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
