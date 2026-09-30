<x-app-layout>
@section('page_header')
    <x-page-header title="Resident correction requests" subtitle="Review requested changes before they alter official resident records." />
@endsection

@section('content')
<div class="space-y-5">
    <x-module-tabs type="residents" />
    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="module-toolbar-sticky no-print overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" class="p-3">
            <div class="module-toolbar module-toolbar--one">
                <div class="min-w-0">
                    <label for="status" class="sr-only">Filter correction requests by status</label>
                    <select id="status" name="status" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                        @foreach (['All', 'Pending', 'Approved', 'Rejected', 'Cancelled'] as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ $option === 'All' ? 'All Status' : $option }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="btn btn-neutral" type="submit">Filter</button></noscript>
                </div>
                <div class="flex min-w-0 flex-wrap items-center justify-end gap-2">
                    <span class="text-sm text-slate-500">{{ $changes->total() }} request{{ $changes->total() === 1 ? '' : 's' }}</span>
                </div>
            </div>
        </form>
    </div>

    <div class="table-scroll rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-[900px] divide-y divide-slate-200">
            <caption class="sr-only">Resident correction requests</caption>
            <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3">Resident</th>
                    <th scope="col" class="px-4 py-3">Requested changes</th>
                    <th scope="col" class="px-4 py-3">Submitted</th>
                    <th scope="col" class="px-4 py-3">Review</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($changes as $change)
                    <tr class="align-top">
                        <td class="px-4 py-3 text-sm">
                            <p class="font-semibold text-slate-900">{{ $change->resident?->full_name ?? 'Resident #'.$change->resident_id }}</p>
                            <p class="text-xs text-slate-500">Request #{{ $change->id }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700">
                            @foreach ($change->changes as $field => $value)
                                @php
                                    $label = match ($field) {
                                        'purok_id' => 'Purok',
                                        'household_id' => 'Household',
                                        default => str_replace('_', ' ', $field),
                                    };
                                    $display = match (true) {
                                        $field === 'purok_id' && is_scalar($value) => $purokNames[$value] ?? 'Purok #'.$value,
                                        $field === 'household_id' && is_scalar($value) => $householdCodes[$value] ?? 'Household #'.$value,
                                        is_scalar($value) => $value,
                                        default => 'selected',
                                    };
                                @endphp
                                <span class="mb-1 mr-2 inline-flex rounded bg-slate-100 px-2 py-1 text-xs"><strong>{{ $label }}:</strong> {{ $display }}</span>
                            @endforeach
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $change->created_at->format('M j, Y g:i A') }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ ['Pending' => 'bg-amber-100 text-amber-800', 'Approved' => 'bg-emerald-100 text-emerald-800', 'Rejected' => 'bg-red-100 text-red-800', 'Cancelled' => 'bg-slate-100 text-slate-700'][$change->status] ?? 'bg-slate-100 text-slate-700' }}">{{ $change->status }}</span>
                            @if ($change->review_note)<p class="mt-2 max-w-xs text-xs text-slate-600">{{ $change->review_note }}</p>@endif
                            @if (auth()->user()?->hasPermission('resident-changes.decide') && $change->status === 'Pending')
                                <div class="mt-3 flex max-w-xs flex-col gap-2">
                                    <form method="POST" action="{{ route('admin.resident-changes.approve', $change) }}" class="flex gap-2"
                                        data-confirm="Approve these changes for {{ $change->resident?->full_name ?? 'this resident' }}?"
                                        data-confirm-title="Approve correction"
                                        data-confirm-accept="Approve" data-confirm-tone="primary" data-confirm-icon="check-circle">
                                        @csrf
                                        <input name="review_note" maxlength="1000" placeholder="Approval note (optional)" aria-label="Approval note (optional)" class="min-h-11 min-w-0 flex-1 rounded-lg border border-slate-300 text-xs focus:border-emerald-500 focus:ring-sky-600">
                                        <button type="submit" class="btn btn-primary btn-row">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.resident-changes.reject', $change) }}" class="flex gap-2"
                                        data-confirm="Reject the correction request for {{ $change->resident?->full_name ?? 'this resident' }}?"
                                        data-confirm-title="Reject correction"
                                        data-confirm-accept="Reject" data-confirm-icon="x-circle">
                                        @csrf
                                        <input name="review_note" required maxlength="1000" placeholder="Reason required" aria-label="Reason for rejection (required)" class="min-h-11 min-w-0 flex-1 rounded-lg border border-slate-300 text-xs focus:border-red-500 focus:ring-red-500">
                                        <button type="submit" class="btn btn-danger btn-row">Reject</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No {{ $status === 'All' ? '' : strtolower($status).' ' }}correction requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $changes->withQueryString()->links() }}
</div>
@endsection
</x-app-layout>
