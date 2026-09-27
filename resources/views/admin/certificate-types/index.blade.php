@extends('layouts.app')

@section('title', 'Certificate types')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Certificate types</h1>
            <p class="mt-1 text-sm text-slate-500">
                The catalog behind the counter and the request queue: codes, titles and fees for every certificate the barangay issues.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button"
                    data-open-modal="type-new"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                + New type
            </button>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Code</th>
                    <th class="px-5 py-3">Title</th>
                    <th class="px-5 py-3">Type</th>
                    <th class="px-5 py-3 text-right">Fee ₱</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Issued</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($documents as $document)
                    <tr>
                        <td class="px-5 py-3.5">
                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-700">
                                {{ $document->code }}
                            </span>
                        </td>

                        <td class="px-5 py-3.5">
                            <p class="font-medium text-slate-900">{{ $document->title }}</p>
                            @if ($document->description)
                                <p class="mt-0.5 max-w-md text-xs text-slate-500">{{ $document->description }}</p>
                            @endif
                        </td>

                        <td class="px-5 py-3.5 text-slate-600">{{ $document->document_type }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">{{ number_format((float) $document->fee, 2) }}</td>

                        <td class="px-5 py-3.5">
                            @switch($document->status)
                                @case('Active')
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                                    @break
                                @case('Draft')
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Draft</span>
                                    @break
                                @default
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Inactive</span>
                            @endswitch
                        </td>

                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">{{ $document->issuances_count }}</td>

                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap justify-end gap-2">
                                <button type="button"
                                        data-open-modal="type-edit-{{ $document->id }}"
                                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                    Edit
                                </button>
                                <button type="button"
                                        data-open-modal="type-delete-{{ $document->id }}"
                                        class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-8 text-center text-sm text-slate-500">
                            No certificate types yet — add the first one with “+ New type”.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $documents->links() }}
    </div>

    {{-- ── dialogs: new, then edit / delete per row ── --}}
    @include('admin.certificate-types.dialog', ['document' => null])

    @foreach ($documents as $document)
        @include('admin.certificate-types.dialog', ['document' => $document])

        <div id="type-delete-{{ $document->id }}"
             data-modal
             role="dialog"
             aria-modal="true"
             aria-labelledby="type-delete-{{ $document->id }}-title"
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

                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-red-400">Delete certificate type</p>
                        <h2 id="type-delete-{{ $document->id }}-title" class="mt-2 text-xl font-semibold text-slate-900">
                            {{ $document->code }} — {{ $document->title }}
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">
                            Remove this type from the catalog? It can only be deleted while no
                            certificate has been issued under it and no resident has requested it online.
                            Otherwise, set the status to Inactive instead.
                        </p>

                        <form method="POST" action="{{ route('admin.certificate-types.destroy', $document) }}"
                              data-submit-loading data-loading-label="Deleting…"
                              class="mt-6">
                            @csrf
                            @method('DELETE')

                            <button type="submit"
                                    class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-200 disabled:cursor-wait disabled:opacity-70">
                                Delete certificate type
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
