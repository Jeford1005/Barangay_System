@extends('layouts.app')

@section('title', 'My requests')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">My requests</h1>
            <p class="mt-1 text-sm text-slate-500">
                <a href="{{ route('resident.portal') }}" class="font-medium text-slate-600 transition hover:text-slate-900">&larr; My barangay</a>
                &mdash; track your certificate and clearance requests.
            </p>
        </div>

        <button type="button"
                data-open-modal="request-modal"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
            + Request a certificate
        </button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Document</th>
                    <th class="px-5 py-3">Purpose</th>
                    <th class="px-5 py-3">Copies</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Requested</th>
                    <th class="px-5 py-3">Reason</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-slate-900">{{ $row->document?->title ?? 'Unknown document' }}</p>
                            <p class="text-slate-500">{{ $row->document?->code ?? '—' }}</p>
                        </td>

                        <td class="max-w-xs px-5 py-3.5 text-slate-700">{{ $row->purpose }}</td>

                        <td class="px-5 py-3.5 text-slate-700">{{ $row->issuance?->copies ?? '—' }}</td>

                        <td class="px-5 py-3.5">
                            @switch($row->status)
                                @case('Pending')
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Pending</span>
                                    @break
                                @case('Approved')
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Approved</span>
                                    @break
                                @case('Rejected')
                                    <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Rejected</span>
                                    @break
                                @default
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Cancelled</span>
                            @endswitch
                        </td>

                        <td class="px-5 py-3.5 text-slate-500">{{ $row->created_at->format('M j, Y') }}</td>

                        <td class="max-w-xs px-5 py-3.5 text-slate-500">
                            {{ $row->rejection_reason ?: '—' }}
                        </td>

                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($row->isPending())
                                    <form method="POST" action="{{ route('resident.requests.cancel', $row) }}">
                                        @csrf
                                        <button type="submit"
                                                data-submit-loading data-loading-label="Cancelling…"
                                                class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-wait disabled:opacity-70">
                                            Cancel
                                        </button>
                                    </form>
                                @elseif ($row->status === 'Approved' && $row->issuance_id !== null)
                                    <a href="{{ route('resident.requests.certificate', $row) }}"
                                       class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                        View certificate
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400">&mdash;</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">
                            You have not requested anything yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $rows->links() }}
    </div>

    {{-- ── Request a certificate dialog — centered on screen ── --}}
    <div id="request-modal"
         data-modal
         role="dialog"
         aria-modal="true"
         aria-labelledby="request-modal-title"
         aria-hidden="true"
         class="fixed inset-0 z-40 hidden"
         @if ($errors->any()) data-open-on-load @endif>
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

                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-400">Certificate requests</p>
                    <h2 id="request-modal-title" class="mt-2 text-xl font-semibold text-slate-900">Request a certificate</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500">
                        Ask for a barangay certificate or clearance online. The office reviews every
                        request and you can pick up the printed copy once it is approved.
                    </p>

                    @if ($documents->isEmpty())
                        <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            No certificates or clearances are available for online requests right now.
                            Please visit the barangay office.
                        </div>
                    @else
                        <form method="POST" action="{{ route('resident.requests.store') }}"
                              data-submit-loading data-loading-label="Submitting…"
                              class="mt-6 space-y-4">
                            @csrf

                            <div>
                                <label for="document_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Document</label>
                                <select id="document_id"
                                        name="document_id"
                                        required
                                        class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('document_id') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                    <option value="">Select a certificate or clearance…</option>
                                    @foreach ($documents as $document)
                                        <option value="{{ $document->id }}"
                                                @selected((string) old('document_id') === (string) $document->id)>
                                            {{ $document->title }} ({{ $document->code }}) &mdash; &#8369;{{ number_format((float) $document->fee, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('document_id')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="purpose" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Purpose</label>
                                <input id="purpose"
                                       type="text"
                                       name="purpose"
                                       value="{{ old('purpose') }}"
                                       required
                                       maxlength="255"
                                       placeholder="e.g. Employment requirement"
                                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('purpose') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                @error('purpose')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="copies" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Copies</label>
                                <select id="copies"
                                        name="copies"
                                        required
                                        class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('copies') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                                    @for ($n = 1; $n <= 5; $n++)
                                        <option value="{{ $n }}" @selected((string) old('copies', '1') === (string) $n)>
                                            {{ $n }} {{ $n === 1 ? 'copy' : 'copies' }}
                                        </option>
                                    @endfor
                                </select>
                                @error('copies')
                                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit"
                                    class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 disabled:cursor-wait disabled:opacity-70">
                                Submit request
                            </button>
                        </form>
                    @endif

                    <div class="mt-5 border-t border-slate-100 pt-4 text-center">
                        <button type="button" data-close-modal class="text-sm text-slate-500 transition hover:text-slate-800">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
