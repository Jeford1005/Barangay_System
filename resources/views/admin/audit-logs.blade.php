<x-app-layout>
@section('page_header')
    <x-page-header title="Audit Log" subtitle="Accountability trail of password-reset activity. Newest first." />
@endsection

@section('content')
<x-settings-shell>
<div class="audit-page">
    <div class="bg-white rounded-xl shadow overflow-hidden">

        {{-- Print-only document header: scope, filters applied, generated date. --}}
        <div class="print-only hidden">
            <p class="mt-4 text-sm text-slate-700">
                <strong>Scope:</strong>
                @if (request()->filled('event'))Event: {{ App\Models\AuditLog::make(['event' => request('event')])->event_label }} · @endif
                @if (request()->filled('actor_type'))Actor: {{ ucfirst(request('actor_type')) }} · @endif
                @if (request()->filled('subject_type'))Record type: {{ ucfirst(request('subject_type')) }} · @endif
                @if (request()->filled('from'))From: {{ request('from') }} · @endif
                @if (request()->filled('to'))To: {{ request('to') }} · @endif
                @if (request()->filled('search'))Search: "{{ request('search') }}" · @endif
                Showing {{ $logs->count() }} of {{ $logs->total() }} record{{ $logs->total() === 1 ? '' : 's' }} (page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}).
            </p>
        </div>

        <!-- Filters -->
        <div class="no-print px-6 pt-6">
            <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="module-toolbar-sticky">
                <div class="flex flex-col gap-3 lg:flex-row lg:flex-nowrap lg:items-start">
                    <div class="min-w-0 flex-1">
                    <div class="module-toolbar-filters module-toolbar-filters--admin">
                <label for="audit-search" class="sr-only">Search audit logs</label>
                <input
                    id="audit-search"
                    type="search"
                    name="search"
                    maxlength="100"
                    value="{{ request('search') }}"
                    placeholder="Search actor, subject, email, or IP…"
                    class="w-full min-h-11 px-3 py-2 border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent"
                >
                <label for="audit-event" class="sr-only">Filter audit logs by event</label>
                <select id="audit-event" name="event" onchange="this.form.submit()" class="w-full min-h-11 px-3 py-2 border border-slate-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                    <option value="">All events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event }}" @selected(request('event') === $event)>{{ App\Models\AuditLog::make(['event' => $event])->event_label }}</option>
                    @endforeach
                </select>
                <label for="audit-actor-type" class="sr-only">Filter audit logs by actor type</label>
                <select id="audit-actor-type" name="actor_type" onchange="this.form.submit()" class="w-full min-h-11 px-3 py-2 border border-slate-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                    <option value="">All actors</option>
                    @foreach (['user' => 'User', 'system' => 'System', 'guest' => 'Guest'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('actor_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label for="audit-subject-type" class="sr-only">Filter audit logs by record type</label>
                <select id="audit-subject-type" name="subject_type" onchange="this.form.submit()" class="w-full min-h-11 px-3 py-2 border border-slate-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                    <option value="">All record types</option>
                    @foreach ($subjectTypes as $subjectType)
                        <option value="{{ $subjectType }}" @selected(request('subject_type') === $subjectType)>{{ ucfirst($subjectType) }}</option>
                    @endforeach
                </select>
                    </div>
                    <div class="module-toolbar-filters module-toolbar-filters--admin mt-2">
                <label for="audit-from" class="sr-only">Filter audit logs from this date</label>
                <input
                    id="audit-from"
                    type="date"
                    name="from"
                    value="{{ request('from') }}"
                    max="{{ now()->toDateString() }}"
                    onchange="this.form.submit()"
                    title="From date"
                    class="w-full min-h-11 px-3 py-2 border border-slate-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent"
                >
                <label for="audit-to" class="sr-only">Filter audit logs up to this date</label>
                <input
                    id="audit-to"
                    type="date"
                    name="to"
                    value="{{ request('to') }}"
                    max="{{ now()->toDateString() }}"
                    onchange="this.form.submit()"
                    title="To date"
                    class="w-full min-h-11 px-3 py-2 border border-slate-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent"
                >
                <noscript><button type="submit" class="btn btn-neutral">Filter</button></noscript>
                <div class="flex gap-2">
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-neutral">
                        Reset
                    </a>
                </div>
                    </div>
                    </div>
                    <div class="no-print flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-3 lg:flex-nowrap xl:ml-3 xl:border-l xl:border-t-0 xl:pl-4 xl:pt-0">
                        <span class="text-sm text-slate-500">{{ $logs->total() }} record{{ $logs->total() === 1 ? '' : 's' }}</span>
                        <x-print-button />
                    </div>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="p-6">
            @if ($logs->isEmpty())
                <div class="text-center py-8 text-slate-500">
                    <x-icon name="clipboard-document-list" class="mx-auto mb-4 h-12 w-12 text-slate-200" />
                    @php $auditFiltered = request()->filled('search') || request()->filled('event') || request()->filled('actor_type') || request()->filled('subject_type') || request()->filled('from') || request()->filled('to'); @endphp
                    <h2 class="mt-2 text-sm font-semibold text-slate-900">No audit records found</h2>
                    <p class="mt-1 text-sm">{{ $auditFiltered ? 'Nothing matches your filters. Try widening the scope or reset them.' : 'Password-reset activity will appear here.' }}</p>
                    @if ($auditFiltered)
                        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-neutral mt-4">Reset filters</a>
                    @endif
                </div>
            @else
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-slate-200 audit-table">
                        <caption class="sr-only">System audit log entries</caption>
                        <thead class="sticky top-0 z-10 bg-slate-50">
                            <tr>
                                <th scope="col" class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">When</th>
                                <th scope="col" class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Event</th>
                                <th scope="col" class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actor</th>
                                <th scope="col" class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Subject</th>
                                <th scope="col" class="hidden print:table-cell md:table-cell px-3 sm:px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">IP Address</th>
                                <th scope="col" class="hidden print:table-cell md:table-cell px-3 sm:px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Device</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            @foreach ($logs as $log)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 sm:px-4 py-3 whitespace-nowrap text-sm text-slate-500" title="{{ $log->occurred_at->format('M j, Y g:i:s A') }}">
                                        {{ $log->occurred_at->format('M j, Y g:i A') }}
                                        <span class="mt-1 block text-xs text-slate-500 md:hidden">{{ e($log->ip_address ?? '—') }} &middot; {{ e(\Illuminate\Support\Str::limit($log->user_agent ?? '—', 30)) }}</span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                            {{ $log->event === 'password_reset.completed' ? 'bg-emerald-100 text-emerald-800' : ($log->event === 'password_reset.failed_code' ? 'bg-red-100 text-red-800' : 'bg-sky-100 text-sky-800') }}">
                                            {{ $log->event_label }}
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-4 py-3 whitespace-nowrap text-sm text-slate-900">
                                        {{ $log->actor_email ?? data_get($log->properties, 'actor_email') ?? (($log->actor_type ?? 'guest') === 'guest' ? 'Guest' : 'System') }}
                                    </td>
                                    <td class="px-3 sm:px-4 py-3 whitespace-nowrap text-sm text-slate-900">
                                        {{ $log->subject_label ?? $log->user_email ?? '—' }}
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-4 py-3 whitespace-nowrap text-sm text-slate-500">{{ $log->ip_address ?? '—' }}</td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-4 py-3 max-w-xs truncate text-sm text-slate-500" title="{{ $log->user_agent }}">
                                        {{ $log->user_agent ? \Illuminate\Support\Str::limit($log->user_agent, 60) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $logs->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Certification footer for barangay record-keeping (print only). --}}
    <div class="print-only hidden mt-8 max-w-5xl mx-auto" aria-hidden="true">
        <p class="text-sm text-slate-700 leading-relaxed">
            I certify that this is a true and correct extract of the audit log of the
            Barangay Management System, covering the entries shown above
            @if (request()->filled('event') || request()->filled('search') || request()->filled('actor_type') || request()->filled('subject_type') || request()->filled('from') || request()->filled('to'))as filtered by the stated scope @endif
            as of {{ now()->format('M j, Y') }}.
        </p>
        <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-8 max-w-2xl">
            <div class="text-center">
                <div class="border-t border-slate-800 pt-1">Prepared by (name &amp; signature)</div>
            </div>
            <div class="text-center">
                <div class="border-t border-slate-800 pt-1">Punong Barangay</div>
            </div>
        </div>
    </div>
</x-settings-shell>
@endsection
</x-app-layout>
