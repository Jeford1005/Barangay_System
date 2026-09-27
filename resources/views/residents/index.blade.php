@extends('layouts.app')

@section('title', 'Residents')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Residents</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ number_format($activeCount) }} active &middot; {{ number_format($archivedCount) }} archived
                &mdash; registry of Barangay Bidduang.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('residents.directory') }}"
               class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Directory
            </a>

            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.exports', ['dataset' => 'residents']) }}"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Export CSV
                </a>

                <a href="{{ route('residents.create') }}"
                   class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                    + New resident
                </a>
            @endif
        </div>
    </div>

    {{-- ── search + filters ── --}}
    <form method="GET" action="{{ route('residents.index') }}"
          class="mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm print:hidden">
        <div class="min-w-56 flex-1">
            <label for="search" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
            <input id="search"
                   type="search"
                   name="search"
                   value="{{ $filters['search'] }}"
                   placeholder="Name, address or phone"
                   maxlength="100"
                   autocomplete="off"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        </div>

        <div>
            <label for="purok_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Purok</label>
            <select id="purok_id" name="purok_id"
                    class="mt-1.5 block w-52 rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">All puroks</option>
                @foreach ($puroks as $purok)
                    <option value="{{ $purok->id }}" @selected($filters['purok_id'] !== null && $filters['purok_id'] == $purok->id)>{{ $purok->label() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
            <select id="status" name="status"
                    class="mt-1.5 block w-40 rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                @foreach (['Active', 'Archived', 'All'] as $option)
                    <option value="{{ $option }}" @selected($filters['status'] === $option)>{{ $option === 'All' ? 'All statuses' : $option }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                Apply
            </button>
            <a href="{{ route('residents.index') }}"
               class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                Reset
            </a>
        </div>
    </form>

    {{-- ── results ── --}}
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Full name</th>
                    <th class="px-5 py-3">Civil status</th>
                    <th class="px-5 py-3">Purok</th>
                    <th class="px-5 py-3">Household</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($residents as $resident)
                    <tr>
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-slate-900">{{ $resident->full_name }}</p>
                            <p class="text-xs text-slate-500">{{ $resident->age }} yrs &middot; {{ $resident->sex }}</p>
                        </td>

                        <td class="px-5 py-3.5 text-slate-600">{{ $resident->civil_status }}</td>

                        <td class="px-5 py-3.5 text-slate-600">
                            {{ $resident->purok?->name ?? '—' }}
                        </td>

                        <td class="px-5 py-3.5 text-slate-600">
                            {{ $resident->household?->household_number ?? '—' }}
                        </td>

                        <td class="px-5 py-3.5">
                            @if ($resident->isArchived())
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Archived</span>
                            @else
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                            @endif
                        </td>

                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a href="{{ route('residents.show', $resident) }}"
                                   class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                    View
                                </a>

                                @if (auth()->user()->hasPermission('residents.manage'))
                                    <a href="{{ route('residents.edit', $resident) }}"
                                       class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                        Edit
                                    </a>
                                @endif

                                @if (auth()->user()->isAdmin())
                                    @if ($resident->isArchived())
                                        <form method="POST" action="{{ route('residents.restore', $resident) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="rounded-md bg-sky-600 px-3 py-1 text-xs font-medium text-white transition hover:bg-sky-700">
                                                Restore
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('residents.archive', $resident) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="rounded-md border border-red-200 px-3 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50">
                                                Archive
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">
                            No residents matched your filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $residents->links() }}
    </div>
@endsection
