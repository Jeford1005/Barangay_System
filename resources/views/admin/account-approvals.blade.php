<x-app-layout>
@section('page_header')
    <x-page-header title="Account Approvals" subtitle="Resident applications awaiting review.">
        <x-slot:actions>
            <span class="rounded-full px-3 py-1 text-sm font-medium {{ $pending->total() ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-500' }}">
                {{ $pending->total() }} pending
            </span>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
<x-settings-shell>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <div class="space-y-4">
            @forelse ($pending as $applicant)
                @php
                    $profile = $applicant->residentProfile;
                    $application = $applicant->residentApplication;
                @endphp
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-50 text-sm font-semibold text-sky-700">
                                    {{ mb_substr($applicant->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="font-medium text-slate-900">{{ $applicant->name }}</p>
                                    <p class="text-sm text-slate-500">{{ $applicant->email }}</p>
                                </div>
                            </div>

                            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                                @if ($profile)
                                    <div>
                                        <dt class="text-slate-500">Birth date</dt>
                                        <dd class="text-slate-800">{{ optional($profile->birth_date)->format('M j, Y') ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500">Sex</dt>
                                        <dd class="text-slate-800">{{ $profile->sex ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500">Civil status</dt>
                                        <dd class="text-slate-800">{{ $profile->civil_status ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500">Phone</dt>
                                        <dd class="text-slate-800">{{ $profile->phone_number ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500">Purok</dt>
                                        <dd class="text-slate-800">{{ $profile->purok?->name ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500">Address</dt>
                                        <dd class="truncate text-slate-800" title="{{ $profile->address }}">{{ $profile->address ?? '—' }}</dd>
                                    </div>
                                @elseif ($application)
                                    <div>
                                        <dt class="text-slate-500">Birth date</dt>
                                        <dd class="text-slate-800">{{ optional($application->birth_date)->format('M j, Y') ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500">Sex</dt>
                                        <dd class="text-slate-800">{{ $application->sex ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500">Civil status</dt>
                                        <dd class="text-slate-800">{{ $application->civil_status ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500">Phone</dt>
                                        <dd class="text-slate-800">{{ $application->phone_number ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500">Purok</dt>
                                        <dd class="text-slate-800">{{ $application->purok?->name ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500">Address</dt>
                                        <dd class="truncate text-slate-800" title="{{ $application->address }}">{{ $application->address ?? '—' }}</dd>
                                    </div>
                                @else
                                    <div class="col-span-2 sm:col-span-3 text-slate-500">No resident application or profile is available.</div>
                                @endif
                                <div>
                                    <dt class="text-slate-500">Applied</dt>
                                    <dd class="text-slate-800">{{ $applicant->created_at->format('M j, Y g:i A') }}</dd>
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
                                    class="w-full min-h-11 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
                                    Approve
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.approvals.reject', $applicant) }}" class="space-y-2"
                                data-confirm="Reject this application? The reason will be emailed to the applicant."
                                data-confirm-title="Reject application"
                                data-confirm-accept="Reject" data-confirm-icon="x-circle"
                                data-confirm-tone="primary">
                                @csrf
                                <button type="submit"
                                    class="w-full min-h-11 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 shadow-sm transition-colors hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                    Reject
                                </button>
                                <label for="rejection-reason-{{ $applicant->id }}" class="sr-only">Reason for rejecting {{ $applicant->name }}</label>
                                <textarea id="rejection-reason-{{ $applicant->id }}" name="reason" rows="2" required minlength="5" maxlength="500"
                                    placeholder="Reason (emailed to the applicant)…"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">{{ old('reason') }}</textarea>
                                @error('reason')
                                    <p class="text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
                    <p class="text-sm font-medium text-slate-900">No pending applications</p>
                    <p class="mt-1 text-sm text-slate-500">New resident sign-ups will appear here for review.</p>
                </div>
            @endforelse

            @if ($pending->hasPages())
                <div class="border-t border-slate-200 pt-4">{{ $pending->links() }}</div>
            @endif
        </div>

        @if ($recentDecisions->isNotEmpty())
            <h2 class="mb-3 mt-10 text-lg font-semibold tracking-tight">Recent decisions</h2>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Applicant</th>
                            <th class="px-4 py-3">Decision</th>
                            <th class="px-4 py-3">Reviewed by</th>
                            <th class="px-4 py-3">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($recentDecisions as $decided)
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-slate-900">{{ $decided->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $decided->email }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($decided->status === 'approved')
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Approved</span>
                                    @else
                                        <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700" title="{{ $decided->rejection_reason }}">Rejected</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $decided->reviewer?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ optional($decided->approved_at)->format('M j, Y g:i A') }}</td>
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
