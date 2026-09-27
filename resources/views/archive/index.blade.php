@extends('layouts.app')

@section('title', 'Archive')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Archive &mdash; archived resident records</h1>
            <p class="mt-1 text-sm text-slate-500">
                Records taken out of the active directory. Restore them to bring them back, or delete them permanently.
            </p>
        </div>
    </div>

    <form method="GET" action="{{ route('archive.index') }}" class="mb-6 flex flex-wrap items-center gap-3">
        <label for="archive_search" class="sr-only">Search archived residents</label>
        <input id="archive_search"
               type="search"
               name="search"
               value="{{ $search }}"
               maxlength="100"
               placeholder="Search name, address or phone…"
               autocomplete="off"
               class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 sm:w-80">

        <button type="submit"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            Search
        </button>

        @if ($search !== '')
            <a href="{{ route('archive.index') }}" class="text-sm text-slate-500 transition hover:text-slate-800">Clear</a>
        @endif
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Name</th>
                    <th class="px-5 py-3">Sex / Age</th>
                    <th class="px-5 py-3">Purok</th>
                    <th class="px-5 py-3">Address</th>
                    <th class="px-5 py-3">Archived</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($residents as $resident)
                    <tr>
                        <td class="px-5 py-3.5 font-medium text-slate-900">{{ $resident->full_name }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-slate-600">{{ $resident->sex }} / {{ $resident->age }}</td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $resident->purok?->name ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $resident->resolvedAddress() !== '' ? $resident->resolvedAddress() : '—' }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-slate-500">
                            {{ $resident->updated_at->format('M j, Y g:i A') }}
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <form method="POST" action="{{ route('archive.residents.restore', $resident) }}"
                                      data-submit-loading data-loading-label="Restoring…">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-sky-700 disabled:cursor-wait disabled:opacity-70">
                                        Restore
                                    </button>
                                </form>

                                <button type="button"
                                        data-open-modal="archive-delete-modal-{{ $resident->id }}"
                                        class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50">
                                    Delete permanently
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">
                            @if ($search !== '')
                                No archived records match &ldquo;{{ $search }}&rdquo;.
                            @else
                                The archive is empty &mdash; no resident records are archived.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $residents->links() }}
    </div>

    {{-- ── Permanent deletion confirmation, one dialog per row ── --}}
    @foreach ($residents as $resident)
        <div id="archive-delete-modal-{{ $resident->id }}"
             data-modal
             role="dialog"
             aria-modal="true"
             aria-labelledby="archive-delete-title-{{ $resident->id }}"
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

                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-red-400">Confirmation</p>
                        <h2 id="archive-delete-title-{{ $resident->id }}" class="mt-2 text-xl font-semibold text-slate-900">
                            Delete this record permanently?
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">
                            <span class="font-medium text-slate-700">{{ $resident->full_name }}</span>
                            will be erased from the system for good. This cannot be undone — archived records can normally be
                            restored, but a permanent delete removes all trace of this resident.
                        </p>

                        <form method="POST" action="{{ route('archive.residents.destroy', $resident) }}"
                              data-submit-loading data-loading-label="Deleting…"
                              class="mt-6">
                            @csrf
                            @method('DELETE')

                            <button type="submit"
                                    class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-200 disabled:cursor-wait disabled:opacity-70">
                                Delete permanently
                            </button>
                        </form>

                        <div class="mt-5 border-t border-slate-100 pt-4 text-center">
                            <button type="button" data-close-modal class="text-sm text-slate-500 transition hover:text-slate-800">
                                Keep it archived
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection
