<x-app-layout>
@section('page_header')
    <x-page-header title="Certificate Requests" subtitle="{{ $pendingCount }} pending review" />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow overflow-hidden">

        <div class="p-6">
            <form method="GET" action="{{ route('admin.certificate-requests.index') }}">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label for="certificate-request-status" class="sr-only">Filter certificate requests by status</label>
                    <select id="certificate-request-status" name="status"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 sm:w-56">
                        @foreach (['' => 'Pending + reviewed', 'Pending' => 'Pending', 'Approved' => 'Approved', 'Rejected' => 'Rejected', 'Cancelled' => 'Cancelled'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-blue-500">Filter</button>
                    <div class="no-print flex flex-wrap items-center justify-end gap-2 border-t border-neutral-200 pt-3 sm:ml-auto sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0">
                        <a href="{{ route('certificates.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-2 text-sm font-medium text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500">Issued certificates →</a>
                    </div>
                </div>
            </form>

            @if($requests->isEmpty())
                <div class="text-center py-8 text-gray-500">
                    <x-icon name="inbox" class="mx-auto mb-4 h-12 w-12 text-gray-200" />
                    <p class="mt-2">No certificate requests{{ request('status') ? ' with this status' : ' yet' }}.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resident</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificate</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="no-print px-3 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($requests as $req)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 sm:px-6 py-4">
                                        <span class="font-medium text-gray-900">{{ e($req->resident?->full_name ?? '—') }}</span>
                                        @if ($req->resident?->purok)
                                            <span class="block text-xs text-gray-500">{{ e($req->resident->purok->name) }}</span>
                                        @endif
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ e($req->document?->title) }}
                                        <span class="block text-xs text-gray-500">{{ $req->copies }} cop{{ $req->copies === 1 ? 'y' : 'ies' }}</span>
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4 text-sm text-gray-500">{{ e(Str::limit($req->purpose, 40)) }}</td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $req->created_at->format('M j, Y') }}</td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                            {{ ['Pending' => 'bg-yellow-100 text-yellow-800', 'Approved' => 'bg-green-100 text-green-800', 'Rejected' => 'bg-red-100 text-red-800', 'Cancelled' => 'bg-gray-100 text-gray-600'][$req->status] }}">
                                            {{ $req->status }}
                                        </span>
                                        @if ($req->status === 'Approved' && $req->issuance)
                                            <span class="block text-xs text-gray-500 mt-0.5">{{ $req->issuance->control_number }}</span>
                                        @elseif ($req->status === 'Rejected' && $req->rejection_reason)
                                            <span class="block text-xs text-gray-500 mt-0.5">{{ e(Str::limit($req->rejection_reason, 30)) }}</span>
                                        @endif
                                    </td>
                                    <td class="no-print px-3 sm:px-6 py-2 text-right text-sm font-medium whitespace-normal">
                                        @if (auth()->user()?->isAdmin() && $req->status === 'Pending')
                                            <div class="inline-flex items-center gap-1">
                                                <form method="POST" action="{{ route('admin.certificate-requests.approve', $req) }}" class="inline-flex items-center gap-1"
                                                    onsubmit="if(this.fee && this.fee.value === '') this.fee.disabled = true;">
                                                    @csrf
                                                    <label for="certificate-fee-{{ $req->id }}" class="sr-only">Optional fee for {{ $req->document?->title }}</label>
                                                    <input id="certificate-fee-{{ $req->id }}" type="number" name="fee" value="{{ old('fee') }}" placeholder="{{ number_format((float) $req->document?->fee ?? 0, 2) }}" min="0" max="9999" step="0.01" inputmode="decimal"
                                                        class="w-28 rounded-md border border-gray-300 px-2 py-1.5 text-xs" title="Optional: adjust or waive the fee (blank = standard)">
                                                    <button type="submit" class="inline-flex items-center justify-center min-h-9 rounded-md border border-green-200 bg-white px-3 text-sm font-medium text-green-700 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-500">
                                                        Approve
                                                    </button>
                                                </form>
                                                <details class="relative inline-block text-left">
                                                    <summary class="inline-flex items-center justify-center min-h-9 cursor-pointer rounded-md border border-red-200 bg-white px-3 text-sm font-medium text-red-600 hover:bg-red-50 list-none">Reject…</summary>
                                                    <div class="relative z-10 mt-2 w-72 rounded-lg border border-gray-200 bg-white p-3 text-left shadow-lg">
                                                        <form method="POST" action="{{ route('admin.certificate-requests.reject', $req) }}">
                                                            @csrf
                                                            <label for="certificate-rejection-{{ $req->id }}" class="block text-xs font-medium text-gray-600 mb-1">Reason (sent to the resident by email)</label>
                                                            <textarea id="certificate-rejection-{{ $req->id }}" name="rejection_reason" required rows="3" maxlength="1000" placeholder="e.g. Requires a barangay hearing first"
                                                                class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-xs">{{ old('rejection_reason') }}</textarea>
                                                            @error('rejection_reason')
                                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                            @enderror
                                                            <button type="submit" class="mt-2 w-full rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                                                Reject & notify
                                                            </button>
                                                        </form>
                                                    </div>
                                                </details>
                                            </div>
                                        @elseif ($req->status === 'Approved' && $req->issuance)
                                            <a href="{{ route('certificates.print', $req->issuance) }}" class="inline-flex items-center justify-center min-h-9 rounded-md border border-blue-200 bg-white px-3 text-sm font-medium text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                Reprint
                                            </a>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $requests->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
</x-app-layout>
