@extends('layouts.app')

@section('title', 'Certificates')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Certificates</h1>
            <p class="mt-1 text-sm text-slate-500">
                Issue barangay certificates and clearances over the counter, print them, and keep the register of everything issued.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if (auth()->user()->hasPermission('certificates.issue'))
                <a href="{{ route('certificates.create') }}"
                   class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                    + New certificate
                </a>
            @endif

            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.exports', ['dataset' => 'certificates']) }}"
                   class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Export CSV
                </a>
            @endif
        </div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Issued today</p>
            <p class="mt-1 text-2xl font-semibold text-sky-900">{{ $todayCount }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 sm:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">In this register</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $rows->total() }}</p>
            <p class="mt-0.5 text-xs text-slate-400">Issued and voided certificates on record.</p>
        </div>
    </div>

    {{-- ── search + filters ── --}}
    <form method="GET" action="{{ route('certificates.index') }}"
          class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="min-w-56 flex-1">
            <label for="cert-q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
            <input id="cert-q"
                   type="search"
                   name="q"
                   value="{{ $filters['q'] }}"
                   placeholder="Control no., purpose or resident name"
                   autocomplete="off"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        </div>

        <div>
            <label for="cert-status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
            <select id="cert-status" name="status" class="mt-1.5 block w-40 rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="" @selected($filters['status'] === '')>All statuses</option>
                <option value="Issued" @selected($filters['status'] === 'Issued')>Issued</option>
                <option value="Voided" @selected($filters['status'] === 'Voided')>Voided</option>
            </select>
        </div>

        <div>
            <label for="cert-document" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Document</label>
            <select id="cert-document" name="document_id" class="mt-1.5 block w-56 rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="0">All documents</option>
                @foreach ($documents as $document)
                    <option value="{{ $document->id }}" @selected($filters['document_id'] === $document->id)>
                        {{ $document->code }} — {{ $document->title }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                Filter
            </button>

            @if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['document_id'] > 0)
                <a href="{{ route('certificates.index') }}" class="text-sm font-medium text-slate-500 transition hover:text-slate-800">
                    Reset
                </a>
            @endif
        </div>
    </form>

    {{-- ── register ── --}}
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Control No</th>
                    <th class="px-5 py-3">Document</th>
                    <th class="px-5 py-3">Resident</th>
                    <th class="px-5 py-3">Purpose</th>
                    <th class="px-5 py-3 text-right">Fee ₱</th>
                    <th class="px-5 py-3 text-right">Copies</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Issued</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $issuance)
                    <tr @class(['bg-red-50/40' => $issuance->status === 'Voided'])>
                        <td class="px-5 py-3.5 font-medium text-slate-900">{{ $issuance->control_number }}</td>

                        <td class="px-5 py-3.5">
                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                {{ $issuance->document?->code }}
                            </span>
                            <p class="mt-1 text-slate-700">{{ $issuance->document?->title }}</p>
                        </td>

                        <td class="px-5 py-3.5 text-slate-700">{{ $issuance->resident?->full_name ?? '—' }}</td>

                        <td class="px-5 py-3.5 text-slate-600">
                            <span class="block max-w-56 truncate" title="{{ $issuance->purpose }}">{{ $issuance->purpose }}</span>
                        </td>

                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">{{ number_format((float) $issuance->fee, 2) }}</td>
                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">{{ $issuance->copies }}</td>

                        <td class="px-5 py-3.5">
                            @if ($issuance->status === 'Voided')
                                <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Voided</span>
                            @else
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Issued</span>
                            @endif
                        </td>

                        <td class="px-5 py-3.5 text-slate-500">
                            <span class="block text-slate-700">{{ $issuance->issued_at->format('M j, Y') }}</span>
                            <span class="block text-xs text-slate-400">by {{ $issuance->issuer?->name ?? '—' }}</span>
                        </td>

                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a href="{{ route('certificates.print', $issuance) }}"
                                   target="_blank" rel="noopener"
                                   class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-sky-700">
                                    Print
                                </a>

                                @if (auth()->user()->isAdmin() && $issuance->status !== 'Voided')
                                    <button type="button"
                                            data-open-modal="void-{{ $issuance->id }}"
                                            class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50">
                                        Void
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-5 py-8 text-center text-sm text-slate-500">
                            No certificates match this search.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $rows->links() }}
    </div>

    {{-- ── void dialogs (administrator only, one per row) ── --}}
    @foreach ($rows as $issuance)
        @if (auth()->user()->isAdmin() && $issuance->status !== 'Voided')
            <div id="void-{{ $issuance->id }}"
                 data-modal
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="void-{{ $issuance->id }}-title"
                 aria-hidden="true"
                 @if (old('row') == $issuance->id && $errors->has('void_reason')) data-open-on-load @endif
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

                            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-red-400">Void certificate</p>
                            <h2 id="void-{{ $issuance->id }}-title" class="mt-2 text-xl font-semibold text-slate-900">
                                {{ $issuance->control_number }}
                            </h2>
                            <p class="mt-2 text-sm leading-relaxed text-slate-500">
                                Void the certificate issued to
                                <span class="font-medium text-slate-700">{{ $issuance->resident?->full_name ?? 'this resident' }}</span>?
                                The record stays in the register, stamped VOID, and the control number is never reused.
                            </p>

                            <form method="POST" action="{{ route('certificates.void', $issuance) }}"
                                  data-submit-loading data-loading-label="Voiding…"
                                  class="mt-6 space-y-4">
                                @csrf
                                <input type="hidden" name="row" value="{{ $issuance->id }}">

                                <div>
                                    <label for="void-reason-{{ $issuance->id }}" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Reason <span class="text-slate-400 normal-case font-normal">(optional)</span>
                                    </label>
                                    <textarea id="void-reason-{{ $issuance->id }}"
                                              name="void_reason"
                                              rows="3"
                                              maxlength="255"
                                              placeholder="Why is this certificate being voided?"
                                              class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @if (old('row') == $issuance->id && $errors->has('void_reason')) border-red-400 focus:border-red-400 focus:ring-red-400/20 @endif">{{ old('row') == $issuance->id ? old('void_reason') : '' }}</textarea>

                                    @if (old('row') == $issuance->id)
                                        @error('void_reason')
                                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    @endif
                                </div>

                                <button type="submit"
                                        class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-200 disabled:cursor-wait disabled:opacity-70">
                                    Void this certificate
                                </button>
                            </form>

                            <div class="mt-5 border-t border-slate-100 pt-4 text-center">
                                <button type="button" data-close-modal class="text-sm text-slate-500 transition hover:text-slate-800">
                                    Keep it issued
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endsection
