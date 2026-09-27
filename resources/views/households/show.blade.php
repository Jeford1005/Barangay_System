@extends('layouts.app')

@section('title', 'Household '.$household->household_number)

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('households.index') }}" class="text-sm font-medium text-sky-700 transition hover:text-sky-800">
                &larr; Back to households
            </a>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $household->household_number }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $household->address }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" data-print
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Print
            </button>

            @if (auth()->user()->hasPermission('households.manage'))
                <a href="{{ route('households.edit', $household) }}"
                   class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                    Edit household
                </a>
            @endif
        </div>
    </div>

    {{-- ── details ── --}}
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-5">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Household details</h2>
                <p class="mt-1 text-sm text-slate-500">Members are managed from the resident records.</p>
            </div>

            @if ($household->head === null)
                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">
                    No head of household assigned
                </span>
            @endif
        </div>

        <dl class="mt-5 grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Household number</dt>
                <dd class="mt-1 text-sm font-medium text-slate-900">{{ $household->household_number }}</dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Address</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $household->address }}</dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Purok</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $household->purok?->label() ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Head of family</dt>
                <dd class="mt-1 text-sm text-slate-900">
                    @if ($household->head !== null)
                        @if (auth()->user()->hasPermission('residents.view'))
                            <a href="{{ route('residents.show', $household->head) }}" class="font-medium text-sky-700 transition hover:text-sky-800">
                                {{ $household->head->full_name }}
                            </a>
                        @else
                            {{ $household->head->full_name }}
                        @endif
                    @else
                        <span class="text-slate-400">No head of household assigned</span>
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Members</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $household->member_count }}</dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">House type</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $household->house_type }}</dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ownership</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $household->ownership }}</dd>
            </div>

            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1">
                    @switch($household->status)
                        @case('Occupied')
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Occupied</span>
                            @break
                        @case('Vacant')
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">Vacant</span>
                            @break
                        @default
                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">{{ $household->status }}</span>
                    @endswitch
                </dd>
            </div>
        </dl>
    </div>

    {{-- ── members ── --}}
    <div class="mt-8 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-900">Members</h2>
            <p class="mt-0.5 text-sm text-slate-500">Residents currently recorded under this household.</p>
        </div>

        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Name</th>
                    <th class="px-5 py-3">Sex</th>
                    <th class="px-5 py-3">Age</th>
                    <th class="px-5 py-3">Civil status</th>
                    <th class="px-5 py-3">Purok</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($members as $member)
                    <tr>
                        <td class="px-5 py-3.5">
                            @if (auth()->user()->hasPermission('residents.view'))
                                <a href="{{ route('residents.show', $member) }}" class="font-medium text-sky-700 transition hover:text-sky-800">
                                    {{ $member->full_name }}
                                </a>
                            @else
                                <span class="font-medium text-slate-900">{{ $member->full_name }}</span>
                            @endif

                            @if ($household->head !== null && (int) $member->id === (int) $household->head->id)
                                <span class="ml-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">Head</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $member->sex }}</td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $member->age ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $member->civil_status }}</td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $member->purok?->label() ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex justify-end gap-2">
                                @if (auth()->user()->hasPermission('residents.manage'))
                                    <a href="{{ route('residents.edit', $member) }}"
                                       class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                        Edit
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">
                            No residents are recorded under this household yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
