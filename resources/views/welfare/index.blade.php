@extends('layouts.app')

@section('title', 'Welfare assistance')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold text-slate-900">Welfare assistance</h1>
                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">
                    {{ $pendingCount }} awaiting review
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                Assistance requests from residents and their review status.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.exports', ['dataset' => 'welfare']) }}"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Export CSV
                </a>
            @endif

            @if (auth()->user()->hasPermission('welfare.intake'))
                <a href="{{ route('welfare.create') }}"
                   class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                    + New assistance request
                </a>
            @endif
        </div>
    </div>

    {{-- ── search + filters ── --}}
    <form method="GET" action="{{ route('welfare.index') }}"
          class="mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="min-w-56 flex-1">
            <label for="q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
            <input id="q"
                   type="search"
                   name="q"
                   value="{{ $search }}"
                   placeholder="Resident name"
                   maxlength="100"
                   autocomplete="off"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        </div>

        <div>
            <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
            <select id="status"
                    name="status"
                    class="mt-1.5 block w-48 rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">All statuses</option>
                @foreach ($statuses as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="assistance_type" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Assistance type</label>
            <select id="assistance_type"
                    name="assistance_type"
                    class="mt-1.5 block w-48 rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">All types</option>
                @foreach ($assistanceTypes as $option)
                    <option value="{{ $option }}" @selected($assistanceType === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                Apply
            </button>
            <a href="{{ route('welfare.index') }}"
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
                    <th class="px-5 py-3">Resident</th>
                    <th class="px-5 py-3">Assistance type</th>
                    <th class="px-5 py-3">Requested</th>
                    <th class="px-5 py-3">Granted</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Request date</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $record)
                    <tr>
                        <td class="px-5 py-3.5">
                            @if ($record->resident !== null)
                                <a href="{{ route('residents.show', $record->resident) }}"
                                   class="font-medium text-sky-700 transition hover:text-sky-800">
                                    {{ $record->resident->full_name }}
                                </a>
                            @else
                                <span class="font-medium text-slate-900">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $record->assistance_type }}</td>
                        <td class="px-5 py-3.5 text-slate-700">
                            {{ $record->requested_amount !== null ? '₱'.number_format((float) $record->requested_amount, 2) : '—' }}
                        </td>
                        <td class="px-5 py-3.5 text-slate-700">
                            {{ $record->amount !== null ? '₱'.number_format((float) $record->amount, 2) : '—' }}
                        </td>
                        <td class="px-5 py-3.5">
                            @switch($record->status)
                                @case('Requested')
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">Requested</span>
                                    @break
                                @case('Under Review')
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Under Review</span>
                                    @break
                                @case('Approved')
                                    <span class="rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-800">Approved</span>
                                    @break
                                @case('Denied')
                                    <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Denied</span>
                                    @break
                                @default
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">{{ $record->status }}</span>
                            @endswitch
                        </td>
                        <td class="px-5 py-3.5 text-slate-500">{{ $record->request_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if (auth()->user()->isAdmin())
                                    <a href="{{ route('welfare.edit', $record) }}"
                                       class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                        Edit
                                    </a>
                                    <button type="button"
                                            data-open-modal="delete-welfare-{{ $record->id }}"
                                            class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50">
                                        Delete
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-8 text-center text-sm text-slate-500">
                            No assistance requests found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $rows->links() }}
    </div>

    {{-- ── delete confirmation dialogs — one per row, centered on screen ── --}}
    @foreach ($rows as $record)
        <div id="delete-welfare-{{ $record->id }}"
             data-modal
             role="dialog"
             aria-modal="true"
             aria-labelledby="delete-welfare-title-{{ $record->id }}"
             aria-hidden="true"
             class="fixed inset-0 z-40 hidden">
            <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" data-close-modal></div>

            <div class="absolute inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
                    <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7">

                        <button type="button"
                                data-close-modal
                                aria-label="Close dialog"
                                class="absolute right-4 top-4 rounded-md p-1.5 text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" width="18" height="18">
                                <path d="M18 6 6 18M6 6l12 12"/>
                            </svg>
                        </button>

                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-400">Welfare</p>
                        <h2 id="delete-welfare-title-{{ $record->id }}" class="mt-2 text-xl font-semibold text-slate-900">
                            Delete this assistance record?
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">
                            The request of {{ $record->resident?->full_name ?? 'this resident' }} for
                            {{ $record->assistance_type }} assistance will be removed permanently.
                        </p>

                        <form method="POST" action="{{ route('welfare.destroy', $record) }}"
                              data-submit-loading data-loading-label="Deleting…"
                              class="mt-6">
                            @csrf
                            @method('DELETE')

                            <button type="submit"
                                    class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-200 disabled:cursor-wait disabled:opacity-70">
                                Delete record
                            </button>
                        </form>

                        <div class="mt-5 border-t border-slate-100 pt-4 text-center">
                            <button type="button" data-close-modal class="text-sm text-slate-500 transition hover:text-slate-800">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection
