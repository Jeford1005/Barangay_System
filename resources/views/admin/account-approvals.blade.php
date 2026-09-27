<x-app-layout>
@section('page_header')
    <x-page-header title="Account Approvals" subtitle="Resident applications awaiting review." />
@endsection

@section('content')
<x-settings-shell current="approvals">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <div class="no-print mb-4 flex flex-wrap items-center justify-end rounded-xl border border-neutral-200 bg-white p-3 shadow-sm">
            <span class="rounded-full px-3 py-1 text-sm font-medium {{ $pending->total() ? 'bg-blue-50 text-blue-700' : 'bg-neutral-100 text-neutral-500' }}">
                {{ $pending->total() }} pending
            </span>
        </div>
        <div class="space-y-4">
            @forelse ($pending as $applicant)
                @php
                    $profile = $applicant->residentProfile;
                    $application = $applicant->residentApplication;
                @endphp
                <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 text-sm font-semibold text-blue-700">
                                    {{ mb_substr($applicant->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="font-medium text-neutral-900">{{ $applicant->name }}</p>
                                    <p class="text-sm text-neutral-500">{{ $applicant->email }}</p>
                                </div>
                            </div>

                            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                                @if ($profile)
                                    <div>
                                        <dt class="text-neutral-400">Birth date</dt>
                                        <dd class="text-neutral-800">{{ optional($profile->birth_date)->format('M j, Y') ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-neutral-400">Sex</dt>
                                        <dd class="text-neutral-800">{{ $profile->sex ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-neutral-400">Civil status</dt>
                                        <dd class="text-neutral-800">{{ $profile->civil_status ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-neutral-400">Phone</dt>
                                        <dd class="text-neutral-800">{{ $profile->phone_number ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-neutral-400">Purok</dt>
                                        <dd class="text-neutral-800">{{ $profile->purok?->name ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-neutral-400">Address</dt>
                                        <dd class="truncate text-neutral-800" title="{{ $profile->address }}">{{ $profile->address ?? '—' }}</dd>
                                    </div>
                                @elseif ($application)
                                    <div>
                                        <dt class="text-neutral-400">Birth date</dt>
                                        <dd class="text-neutral-800">{{ optional($application->birth_date)->format('M j, Y') ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-neutral-400">Sex</dt>
                                        <dd class="text-neutral-800">{{ $application->sex ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-neutral-400">Civil status</dt>
                                        <dd class="text-neutral-800">{{ $application->civil_status ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-neutral-400">Phone</dt>
                                        <dd class="text-neutral-800">{{ $application->phone_number ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-neutral-400">Purok</dt>
                                        <dd class="text-neutral-800">{{ $application->purok?->name ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-neutral-400">Address</dt>
                                        <dd class="truncate text-neutral-800" title="{{ $application->address }}">{{ $application->address ?? '—' }}</dd>
                                    </div>
                                @else
                                    <div class="col-span-2 sm:col-span-3 text-neutral-400">No resident application or profile is available.</div>
                                @endif
                                <div>
                                    <dt class="text-neutral-400">Applied</dt>
                                    <dd class="text-neutral-800">{{ $applicant->created_at->format('M j, Y g:i A') }}</dd>
                                </div>
                            </dl>

                            @if ($profile && $profile->created_at->ne($applicant->created_at) && $profile->user_id === $applicant->id)
                                <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                                    Linked to an existing resident record (explicit staff link)
                                </p>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-col gap-2 lg:w-64">
                            <form method="POST" action="{{ route('admin.approvals.approve', $applicant) }}">
                                @csrf
                                <button type="submit"
                                    class="w-full min-h-11 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                    Approve
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.approvals.reject', $applicant) }}" class="space-y-2"
                                onsubmit="return confirm('Reject this application? The reason will be emailed to the applicant.');">
                                @csrf
                                <label for="rejection-reason-{{ $applicant->id }}" class="sr-only">Reason for rejecting {{ $applicant->name }}</label>
                                <textarea id="rejection-reason-{{ $applicant->id }}" name="reason" rows="2" required minlength="5" maxlength="500"
                                    placeholder="Reason (emailed to the applicant)…"
                                    class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm placeholder-neutral-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('reason') }}</textarea>
                                @error('reason')
                                    <p class="text-xs text-red-600">{{ $message }}</p>
                                @enderror
                                <button type="submit"
                                    class="w-full min-h-11 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 shadow-sm transition-colors hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                    Reject
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-neutral-300 bg-white p-12 text-center">
                    <p class="text-sm font-medium text-neutral-900">No pending applications</p>
                    <p class="mt-1 text-sm text-neutral-500">New resident sign-ups will appear here for review.</p>
                </div>
            @endforelse

            @if ($pending->hasPages())
                <div class="border-t border-neutral-200 pt-4">{{ $pending->links() }}</div>
            @endif
        </div>

        @if ($recentDecisions->isNotEmpty())
            <h2 class="mb-3 mt-10 text-lg font-semibold tracking-tight">Recent decisions</h2>
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Applicant</th>
                            <th class="px-4 py-3">Decision</th>
                            <th class="px-4 py-3">Reviewed by</th>
                            <th class="px-4 py-3">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($recentDecisions as $decided)
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-neutral-900">{{ $decided->name }}</span>
                                    <span class="block text-xs text-neutral-400">{{ $decided->email }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($decided->status === 'approved')
                                        <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Approved</span>
                                    @else
                                        <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700" title="{{ $decided->rejection_reason }}">Rejected</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-neutral-600">{{ $decided->reviewer?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-neutral-600">{{ optional($decided->approved_at)->format('M j, Y g:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-settings-shell>
@endsection
</x-app-layout>
