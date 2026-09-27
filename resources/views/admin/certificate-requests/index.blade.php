@extends('layouts.app')

@section('title', 'Certificate requests')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Certificate requests</h1>
            <p class="mt-1 text-sm text-slate-500">
                Certificates requested online by residents. Approving one issues the certificate and opens it for printing.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('certificates.index') }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                Issued register
            </a>
        </div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Awaiting review</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $pendingCount }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 sm:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">In this queue</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $rows->total() }}</p>
            <p class="mt-0.5 text-xs text-slate-400">Requests matching the selected status.</p>
        </div>
    </div>

    {{-- ── status tabs ── --}}
    <div class="mb-6 flex flex-wrap gap-2">
        <a href="{{ route('admin.certificate-requests.index') }}"
           @class([
               'rounded-full px-3.5 py-1.5 text-sm font-medium transition',
               'bg-slate-900 text-white' => $status === '',
               'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' => $status !== '',
           ])>
            All <span class="text-xs opacity-70">(except cancelled)</span>
        </a>

        @foreach ($statuses as $tab)
            <a href="{{ route('admin.certificate-requests.index', ['status' => $tab]) }}"
               @class([
                   'rounded-full px-3.5 py-1.5 text-sm font-medium transition',
                   'bg-slate-900 text-white' => $status === $tab,
                   'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' => $status !== $tab,
               ])>
                {{ $tab }}
            </a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Resident</th>
                    <th class="px-5 py-3">Document</th>
                    <th class="px-5 py-3">Purpose</th>
                    <th class="px-5 py-3 text-right">Copies</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Requested</th>
                    <th class="px-5 py-3">Reviewer / Reason</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $requestRow)
                    <tr @class(['bg-amber-50/40' => $requestRow->status === 'Pending'])>
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-slate-900">{{ $requestRow->resident?->full_name ?? '—' }}</p>
                            <p class="text-xs text-slate-500">{{ $requestRow->resident?->email ?? 'No email on file' }}</p>
                        </td>

                        <td class="px-5 py-3.5">
                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                {{ $requestRow->document?->code }}
                            </span>
                            <p class="mt-1 text-slate-700">{{ $requestRow->document?->title }}</p>
                        </td>

                        <td class="px-5 py-3.5 text-slate-600">
                            <span class="block max-w-56 truncate" title="{{ $requestRow->purpose }}">{{ $requestRow->purpose }}</span>
                        </td>

                        <td class="px-5 py-3.5 text-right tabular-nums text-slate-700">—</td>

                        <td class="px-5 py-3.5">
                            @switch($requestRow->status)
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

                        <td class="px-5 py-3.5 text-slate-500">{{ $requestRow->created_at->format('M j, Y') }}</td>

                        <td class="px-5 py-3.5 text-slate-600">
                            @if ($requestRow->reviewed_at)
                                <p class="text-slate-700">{{ $requestRow->reviewer?->name ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ $requestRow->reviewed_at->format('M j, Y g:i A') }}</p>
                                @if ($requestRow->rejection_reason)
                                    <p class="mt-1 max-w-56 text-xs text-red-600" title="{{ $requestRow->rejection_reason }}">{{ $requestRow->rejection_reason }}</p>
                                @endif
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>

                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($requestRow->status === 'Pending' && $requestRow->issuance_id === null)
                                    @if (auth()->user()->isAdmin())
                                        <button type="button"
                                                data-open-modal="approve-{{ $requestRow->id }}"
                                                class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-700">
                                            Approve
                                        </button>
                                        <button type="button"
                                                data-open-modal="reject-{{ $requestRow->id }}"
                                                class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50">
                                            Reject
                                        </button>
                                    @endif
                                @elseif ($requestRow->issuance_id !== null && $requestRow->issuance)
                                    <a href="{{ route('certificates.print', $requestRow->issuance) }}"
                                       target="_blank" rel="noopener"
                                       class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-sky-700">
                                        Print
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-8 text-center text-sm text-slate-500">
                            No certificate requests in this list.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $rows->links() }}
    </div>

    {{-- ── review dialogs (administrator only, one of each per pending row) ── --}}
    @foreach ($rows as $requestRow)
        @if (auth()->user()->isAdmin() && $requestRow->status === 'Pending')
            @php($ownsRow = old('row') == $requestRow->id && $errors->any())

            <div id="approve-{{ $requestRow->id }}"
                 data-modal
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="approve-{{ $requestRow->id }}-title"
                 aria-hidden="true"
                 @if ($ownsRow && $errors->has('fee')) data-open-on-load @endif
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

                            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-600">Approve request</p>
                            <h2 id="approve-{{ $requestRow->id }}-title" class="mt-2 text-xl font-semibold text-slate-900">
                                {{ $requestRow->document?->code }} for {{ $requestRow->resident?->full_name ?? 'this resident' }}
                            </h2>
                            <p class="mt-2 text-sm leading-relaxed text-slate-500">
                                This issues the certificate immediately with the next control number and opens the printable copy.
                            </p>

                            <form method="POST" action="{{ route('admin.certificate-requests.approve', $requestRow) }}"
                                  data-submit-loading data-loading-label="Approving…"
                                  class="mt-6 space-y-4">
                                @csrf
                                <input type="hidden" name="row" value="{{ $requestRow->id }}">

                                <div>
                                    <label for="approve-fee-{{ $requestRow->id }}" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Fee override <span class="text-slate-400 normal-case font-normal">(optional)</span>
                                    </label>
                                    <input id="approve-fee-{{ $requestRow->id }}"
                                           type="number"
                                           name="fee"
                                           value="{{ $ownsRow ? old('fee') : '' }}"
                                           min="0"
                                           max="9999"
                                           step="0.01"
                                           placeholder="Catalog fee"
                                           class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @if ($ownsRow && $errors->has('fee')) border-red-400 focus:border-red-400 focus:ring-red-400/20 @endif">
                                    <p class="mt-1.5 text-xs text-slate-400">Leave blank to use the catalog fee (₱{{ number_format((float) ($requestRow->document?->fee ?? 0), 2) }}).</p>

                                    @if ($ownsRow)
                                        @error('fee')
                                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    @endif
                                </div>

                                <button type="submit"
                                        class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200 disabled:cursor-wait disabled:opacity-70">
                                    Approve &amp; print
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

            <div id="reject-{{ $requestRow->id }}"
                 data-modal
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="reject-{{ $requestRow->id }}-title"
                 aria-hidden="true"
                 @if ($ownsRow && $errors->has('rejection_reason')) data-open-on-load @endif
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

                            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-red-400">Reject request</p>
                            <h2 id="reject-{{ $requestRow->id }}-title" class="mt-2 text-xl font-semibold text-slate-900">
                                {{ $requestRow->document?->code }} for {{ $requestRow->resident?->full_name ?? 'this resident' }}
                            </h2>
                            <p class="mt-2 text-sm leading-relaxed text-slate-500">
                                The resident sees this reason in their portal, so be specific about what they need to fix.
                            </p>

                            <form method="POST" action="{{ route('admin.certificate-requests.reject', $requestRow) }}"
                                  data-submit-loading data-loading-label="Rejecting…"
                                  class="mt-6 space-y-4">
                                @csrf
                                <input type="hidden" name="row" value="{{ $requestRow->id }}">

                                <div>
                                    <label for="reject-reason-{{ $requestRow->id }}" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Reason</label>
                                    <textarea id="reject-reason-{{ $requestRow->id }}"
                                              name="rejection_reason"
                                              rows="4"
                                              required
                                              maxlength="1000"
                                              placeholder="Why can this request not be approved?"
                                              class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @if ($ownsRow && $errors->has('rejection_reason')) border-red-400 focus:border-red-400 focus:ring-red-400/20 @endif">{{ $ownsRow ? old('rejection_reason') : '' }}</textarea>

                                    @if ($ownsRow)
                                        @error('rejection_reason')
                                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    @endif
                                </div>

                                <button type="submit"
                                        class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-200 disabled:cursor-wait disabled:opacity-70">
                                    Reject request
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
        @endif
    @endforeach
@endsection
