@extends('layouts.app')

@section('title', 'Blotter')

@section('content')
    @php
        $statusStyles = [
            'Open' => 'bg-amber-100 text-amber-800',
            'Pending' => 'bg-sky-100 text-sky-800',
            'Resolved' => 'bg-emerald-100 text-emerald-800',
            'Dismissed' => 'bg-slate-200 text-slate-700',
        ];
    @endphp

    <div class="mb-8 flex flex-wrap items-end justify-between gap-4 print:hidden">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Blotter</h1>
            <p class="mt-1 text-sm text-slate-500">
                Record incidents as they are reported, track each case, and print the official case sheet.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.exports', ['dataset' => 'blotter']) }}"
                   class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Export CSV
                </a>
            @endif

            @if (auth()->user()->hasPermission('blotter.manage'))
                <a href="{{ route('blotter.create') }}"
                   class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                    + New entry
                </a>
            @endif
        </div>
    </div>

    {{-- ── chips ── --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-3 print:hidden">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Open or pending</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $openCount }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cases on file</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($rows->total()) }}</p>
        </div>
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Showing</p>
            <p class="mt-1 text-2xl font-semibold text-sky-900">
                {{ $rows->count() }}
                <span class="text-sm font-medium text-sky-700">on this page</span>
            </p>
        </div>
    </div>

    {{-- ── filters ── --}}
    <form method="GET" action="{{ route('blotter.index') }}" class="mb-6 flex flex-wrap items-end gap-3 print:hidden">
        <div class="min-w-[240px] flex-1">
            <label for="blotter-search" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
            <input id="blotter-search"
                   type="search"
                   name="q"
                   value="{{ $q }}"
                   placeholder="Case number, incident, complainant or respondent"
                   maxlength="100"
                   autocomplete="off"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
            @error('q')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="blotter-status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
            <select id="blotter-status"
                    name="status"
                    class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">All statuses</option>
                @foreach (\App\Models\Blotter::STATUSES as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                @endforeach
            </select>
            @error('status')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                Filter
            </button>
            <a href="{{ route('blotter.index') }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Reset
            </a>
        </div>
    </form>

    {{-- ── case table ── --}}
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Case No.</th>
                    <th class="px-5 py-3">Incident</th>
                    <th class="px-5 py-3">Location</th>
                    <th class="px-5 py-3">Complainant</th>
                    <th class="px-5 py-3">Respondent</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Arrest</th>
                    <th class="px-5 py-3 text-right print:hidden">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-3.5">
                            <a href="{{ route('blotter.print', $row) }}"
                               class="font-medium text-sky-700 transition hover:text-sky-900 hover:underline">
                                {{ $row->case_number }}
                            </a>
                            <p class="text-xs text-slate-400">Recorded by {{ $row->recorder?->name ?? '—' }}</p>
                        </td>

                        <td class="px-5 py-3.5">
                            <p class="font-medium text-slate-900">{{ $row->incident_type }}</p>
                            <p class="text-slate-500">
                                {{ $row->incident_date?->format('M j, Y') }}
                                @if ($row->incident_time)
                                    &middot; {{ $row->incident_time->format('H:i') }}
                                @endif
                            </p>
                        </td>

                        <td class="px-5 py-3.5">
                            <p class="text-slate-700">{{ $row->location }}</p>
                            <p class="text-xs text-slate-400">{{ $row->purok?->label() ?? 'No purok' }}</p>
                        </td>

                        <td class="px-5 py-3.5">
                            <p class="text-slate-900">{{ $row->complainant_name }}</p>
                            @if ($row->complainant_contact)
                                <p class="text-xs text-slate-500">{{ $row->complainant_contact }}</p>
                            @endif
                        </td>

                        <td class="px-5 py-3.5">
                            @if ($row->respondent_name)
                                <p class="text-slate-900">{{ $row->respondent_name }}</p>
                                @if ($row->respondent_contact)
                                    <p class="text-xs text-slate-500">{{ $row->respondent_contact }}</p>
                                @endif
                            @else
                                <span class="text-slate-400">&mdash;</span>
                            @endif
                        </td>

                        <td class="whitespace-nowrap px-5 py-3.5">
                            <span class="rounded-full {{ $statusStyles[$row->status] ?? 'bg-slate-100 text-slate-600' }} px-2.5 py-0.5 text-xs font-semibold">
                                {{ $row->status }}
                            </span>
                        </td>

                        <td class="px-5 py-3.5">
                            @if ($row->arrest_made === 'Yes')
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Yes</span>
                            @else
                                <span class="text-slate-500">No</span>
                            @endif
                        </td>

                        <td class="whitespace-nowrap px-5 py-3.5 print:hidden">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a href="{{ route('blotter.print', $row) }}"
                                   class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                    View
                                </a>

                                @if (auth()->user()->hasPermission('blotter.manage'))
                                    <a href="{{ route('blotter.edit', $row) }}"
                                       class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                        Edit
                                    </a>
                                @endif

                                @if (auth()->user()->isAdmin())
                                    {{-- CSS-only two-step confirm: no inline script (CSP). --}}
                                    <form method="POST" action="{{ route('blotter.destroy', $row) }}" class="inline-flex items-center gap-2">
                                        @csrf
                                        @method('DELETE')

                                        <input type="checkbox" id="del-blotter-{{ $row->id }}" class="peer sr-only">

                                        <label for="del-blotter-{{ $row->id }}"
                                               class="cursor-pointer rounded-md border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50 peer-checked:hidden">
                                            Delete
                                        </label>

                                        <button type="submit"
                                                class="hidden rounded-md bg-red-600 px-2.5 py-1 text-xs font-medium text-white transition hover:bg-red-700 peer-checked:inline-block">
                                            Confirm delete
                                        </button>

                                        <label for="del-blotter-{{ $row->id }}"
                                               class="hidden cursor-pointer rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-50 peer-checked:inline-block">
                                            Cancel
                                        </label>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-10 text-center text-sm text-slate-500">
                            No blotter entries match this search.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 print:hidden">
        {{ $rows->links() }}
    </div>
@endsection
