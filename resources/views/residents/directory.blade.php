@extends('layouts.app')

@section('title', 'Resident directory')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4 print:mb-3 print:block">
        <div>
            <a href="{{ route('residents.index') }}"
               class="text-sm font-medium text-sky-700 transition hover:text-sky-800 print:hidden">
                &larr; Back to residents
            </a>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Resident directory</h1>
            <p class="mt-1 text-sm text-slate-500 print:hidden">
                Active residents grouped by purok &middot; {{ number_format($total) }} in total
                @if (filled($search))
                    &middot; matching "{{ $search }}"
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2 print:hidden">
            <button type="button"
                    data-print
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                Print directory
            </button>
        </div>
    </div>

    {{-- ── search ── --}}
    <form method="GET" action="{{ route('residents.directory') }}"
          class="mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm print:hidden">
        <div class="min-w-56 flex-1">
            <label for="search" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
            <input id="search"
                   type="search"
                   name="search"
                   value="{{ $search ?? '' }}"
                   placeholder="Name, address or phone"
                   maxlength="100"
                   autocomplete="off"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        </div>

        <div class="flex gap-2">
            <button type="submit"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                Apply
            </button>
            <a href="{{ route('residents.directory') }}"
               class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                Reset
            </a>
        </div>
    </form>

    {{-- ── printed letterhead ── --}}
    <div class="mb-6 hidden rounded-xl border border-sky-200 bg-sky-50 px-5 py-4 text-center print:block">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-900">Barangay Bidduang &middot; Pamplona, Cagayan</p>
        <p class="mt-1 text-base font-semibold text-slate-900">Resident Directory (Active Residents)</p>
        <p class="mt-1 text-xs text-slate-600">Printed {{ now()->format('M j, Y') }} &middot; {{ number_format($total) }} resident(s)</p>
    </div>

    @forelse ($groups as $group)
        <section class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm print:mb-4 print:break-inside-avoid print:rounded-none print:shadow-none">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-5 py-3 print:bg-white">
                <h2 class="text-sm font-semibold text-slate-900">
                    @if ($group['purok'] !== null)
                        {{ $group['purok']->name }}
                        <span class="ml-1.5 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $group['purok']->code }}</span>
                    @else
                        No Purok Assigned
                    @endif
                </h2>
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {{ count($group['residents']) }} resident(s)
                </span>
            </div>

            @if ($group['residents'] === [])
                <p class="px-5 py-4 text-sm text-slate-500">No residents assigned to this purok.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 print:bg-white">
                            <tr>
                                <th class="px-5 py-2.5">Name</th>
                                <th class="px-5 py-2.5 text-right">Age</th>
                                <th class="px-5 py-2.5">Sex</th>
                                <th class="px-5 py-2.5">Civil status</th>
                                <th class="px-5 py-2.5">Address</th>
                                <th class="px-5 py-2.5">Phone</th>
                                <th class="px-5 py-2.5">Household</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($group['residents'] as $resident)
                                <tr>
                                    <td class="px-5 py-2.5 font-medium text-slate-900">
                                        <a href="{{ route('residents.show', $resident) }}" class="transition hover:text-sky-700 print:text-black print:no-underline">
                                            {{ $resident->full_name }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-2.5 text-right text-slate-600">{{ $resident->age }}</td>
                                    <td class="px-5 py-2.5 text-slate-600">{{ $resident->sex }}</td>
                                    <td class="px-5 py-2.5 text-slate-600">{{ $resident->civil_status }}</td>
                                    <td class="px-5 py-2.5 text-slate-600">{{ $resident->resolvedAddress() ?: '—' }}</td>
                                    <td class="px-5 py-2.5 text-slate-600">{{ $resident->phone ?: '—' }}</td>
                                    <td class="px-5 py-2.5 text-slate-600">{{ $resident->household?->household_number ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @empty
        <p class="rounded-xl border border-slate-200 bg-white px-5 py-8 text-center text-sm text-slate-500 shadow-sm">
            No active residents matched your search.
        </p>
    @endforelse

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 text-sm shadow-sm print:rounded-none print:shadow-none">
        <p class="font-semibold text-slate-900">Grand total: {{ number_format($total) }} active resident(s)</p>
        <p class="text-slate-500 print:hidden">Use the print button above for a paper copy of this directory.</p>
    </div>
@endsection
