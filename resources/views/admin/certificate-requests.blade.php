<x-app-layout>
@section('page_header')
    <x-page-header title="Certificate Requests" subtitle="{{ $pendingCount }} pending review" />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <x-module-tabs type="certificates" class="mb-4" />
    <div class="bg-white rounded-xl shadow overflow-hidden">

        <div class="p-6">
            <form method="GET" action="{{ route('admin.certificate-requests.index') }}">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label for="certificate-request-status" class="sr-only">Filter certificate requests by status</label>
                    <select id="certificate-request-status" name="status" onchange="this.form.submit()"
                        class="w-full min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 sm:w-56">
                        @foreach (['' => 'Pending + reviewed', 'Pending' => 'Pending', 'Approved' => 'Approved', 'Rejected' => 'Rejected', 'Cancelled' => 'Cancelled'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="btn btn-neutral">Filter</button></noscript>
                    <div class="no-print flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 pt-3 sm:ml-auto sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0">
                        <a href="{{ route('certificates.index') }}" class="btn btn-outline">Issued certificates →</a>
                    </div>
                </div>
            </form>

            @if($requests->isEmpty())
                <div class="text-center py-8 text-slate-500">
                    <x-icon name="inbox" class="mx-auto mb-4 h-12 w-12 text-slate-200" />
                    <p class="mt-2">No certificate requests{{ request('status') ? ' with this status' : ' yet' }}.</p>
                </div>
            @else
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-slate-200">
                        <caption class="sr-only">Certificate requests awaiting review</caption>
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Resident</th>
                                <th scope="col" class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Certificate</th>
                                <th scope="col" class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Purpose</th>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Submitted</th>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="no-print px-3 sm:px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            @foreach($requests as $req)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 sm:px-6 py-4">
                                        <span class="font-medium text-slate-900">{{ e($req->resident?->full_name ?? '—') }}</span>
                                        @if ($req->resident?->purok)
                                            <span class="block text-xs text-slate-500">{{ e($req->resident->purok->name) }}</span>
                                        @endif
                                        <span class="mt-1 block text-xs text-slate-500 md:hidden">{{ e($req->document?->title ?? '—') }} &middot; {{ e($req->purpose ?? '—') }}</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                                        {{ e($req->document?->title) }}
                                        <span class="block text-xs text-slate-500">{{ $req->copies }} cop{{ $req->copies === 1 ? 'y' : 'ies' }}</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4 text-sm text-slate-500">{{ e(Str::limit($req->purpose, 40)) }}</td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-slate-500">{{ $req->created_at->format('M j, Y') }}</td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                            {{ ['Pending' => 'bg-amber-100 text-amber-800', 'Approved' => 'bg-emerald-100 text-emerald-800', 'Rejected' => 'bg-red-100 text-red-800', 'Cancelled' => 'bg-slate-100 text-slate-600'][$req->status] }}">
                                            {{ $req->status }}
                                        </span>
                                        @if ($req->status === 'Approved' && $req->issuance)
                                            <span class="block text-xs text-slate-500 mt-0.5">{{ $req->issuance->control_number }}</span>
                                        @elseif ($req->status === 'Rejected' && $req->rejection_reason)
                                            <span class="block text-xs text-slate-500 mt-0.5">{{ e(Str::limit($req->rejection_reason, 30)) }}</span>
                                        @endif
                                    </td>
                                    <td class="no-print px-3 sm:px-6 py-2 text-right text-sm font-medium whitespace-normal">
                                        @if (auth()->user()?->hasPermission('certificate-requests.decide') && $req->status === 'Pending')
                                            <div class="inline-flex items-center gap-2">
                                                <form method="POST" action="{{ route('admin.certificate-requests.approve', $req) }}" class="inline-flex items-center gap-2"
                                                    data-confirm="Approve this certificate request? A certificate will be issued." data-confirm-title="Approve request" data-confirm-accept="Approve" data-confirm-tone="primary" data-confirm-icon="check-circle"
                                                    onsubmit="if(this.fee && this.fee.value === '') this.fee.disabled = true;">
                                                    @csrf
                                                    <label for="certificate-fee-{{ $req->id }}" class="sr-only">Optional fee for {{ $req->document?->title }}</label>
                                                    <input id="certificate-fee-{{ $req->id }}" type="number" name="fee" value="{{ (string) old('_request_id') === (string) $req->id ? old('fee') : '' }}" placeholder="{{ number_format((float) $req->document?->fee ?? 0, 2) }}" min="0" max="9999" step="0.01" inputmode="decimal"
                                                        class="w-28 min-h-11 rounded-lg border border-slate-300 px-2 py-1.5 text-xs" title="Optional: adjust or waive the fee (blank = standard)">
                                                    <button type="submit" class="btn btn-outline-success">
                                                        Approve
                                                    </button>
                                                </form>
                                                <details class="relative inline-block text-left">
                                                    <summary class="btn btn-outline-danger list-none">Reject…</summary>
                                                    <div class="relative z-10 mt-2 w-72 rounded-lg border border-slate-200 bg-white p-3 text-left shadow-lg">
                                                        <form method="POST" action="{{ route('admin.certificate-requests.reject', $req) }}" data-confirm="Reject this certificate request? The reason will be emailed to the resident." data-confirm-title="Reject request" data-confirm-accept="Reject" data-confirm-icon="x-circle">
                                                            @csrf
                                                            <label for="certificate-rejection-{{ $req->id }}" class="block text-xs font-medium text-slate-600 mb-1">Reason (sent to the resident by email)</label>
                                                            <textarea id="certificate-rejection-{{ $req->id }}" name="rejection_reason" required rows="3" maxlength="1000" placeholder="e.g. Requires a barangay hearing first"
                                                                class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">{{ (string) old('_request_id') === (string) $req->id ? old('rejection_reason') : '' }}</textarea>
                                                            @error('rejection_reason')
                                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                            @enderror
                                                            <button type="submit" class="btn btn-danger mt-2 w-full">
                                                                Reject & notify
                                                            </button>
                                                        </form>
                                                    </div>
                                                </details>
                                            </div>
                                        @elseif ($req->status === 'Approved' && $req->issuance)
                                            @if ($req->issuance->status === 'Voided')
                                                <span class="text-xs text-red-600">Voided — no reprint</span>
                                            @else
                                                <a href="{{ route('certificates.print', $req->issuance) }}" class="btn btn-outline">
                                                    <x-icon name="printer" class="h-4 w-4" />
                                                    Reprint
                                                </a>
                                            @endif
                                        @else
                                            <span class="text-xs text-slate-500">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $requests->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
</x-app-layout>
