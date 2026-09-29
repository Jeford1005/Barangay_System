<x-app-layout>
@section('page_header')
    <x-page-header title="Audit Log" subtitle="Accountability trail of password-reset activity. Newest first." />
@endsection

@section('content')
<x-settings-shell current="audit">
<div class="max-w-6xl mx-auto audit-page">
    <div class="bg-white rounded-xl shadow overflow-hidden">

        {{-- Print-only document header: scope, filters applied, generated date. --}}
        <div class="print-only hidden">
            <p class="mt-4 text-sm text-slate-700">
                <strong>Scope:</strong>
                @if (request()->filled('event'))Event: {{ App\Models\AuditLog::make(['event' => request('event')])->event_label }} · @endif
                @if (request()->filled('search'))Search: "{{ request('search') }}" · @endif
                Showing {{ $logs->count() }} of {{ $logs->total() }} record{{ $logs->total() === 1 ? '' : 's' }} (page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}).
            </p>
        </div>

        <!-- Filters -->
        <div class="px-6 pt-6">
            <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="module-toolbar-sticky">
                <div class="flex flex-col gap-3 lg:flex-row lg:flex-nowrap lg:items-center">
                    <div class="module-toolbar-filters module-toolbar-filters--three min-w-0 flex-1">
                <label for="audit-search" class="sr-only">Search audit logs</label>
                <input
                    id="audit-search"
                    type="search"
                    name="search"
                    maxlength="100"
                    value="{{ request('search') }}"
                    placeholder="Search email or IP…"
                    class="w-full min-h-11 px-3 py-2 border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent"
                >
                <label for="audit-event" class="sr-only">Filter audit logs by event</label>
                <select id="audit-event" name="event" onchange="this.form.submit()" class="w-full min-h-11 px-3 py-2 border border-slate-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                    <option value="">All events</option>
                    @foreach ($events as $event)
                        <option value="{{ $event }}" @selected(request('event') === $event)>{{ App\Models\AuditLog::make(['event' => $event])->event_label }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <noscript><button type="submit" class="inline-flex flex-1 items-center justify-center min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-600">Filter</button></noscript>
                    <a href="{{ route('admin.audit-logs.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-600">
                        Reset
                    </a>
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
                    <p>No audit records{{ request()->filled('search') || request()->filled('event') ? ' match your filters' : ' yet' }}.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 audit-table">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">When</th>
                                <th class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Event</th>
                                <th class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actor</th>
                                <th class="px-3 sm:px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Subject</th>
                                <th class="hidden md:table-cell px-3 sm:px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">IP Address</th>
                                <th class="hidden md:table-cell px-3 sm:px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Device</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            @foreach ($logs as $log)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 sm:px-4 py-3 whitespace-nowrap text-sm text-slate-500" title="{{ $log->occurred_at->format('Y-m-d H:i:s') }}">
                                        {{ $log->occurred_at->format('M d, Y H:i') }}
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
                                    <td class="hidden md:table-cell px-3 sm:px-4 py-3 whitespace-nowrap text-sm text-slate-500">{{ $log->ip_address ?? '—' }}</td>
                                    <td class="hidden md:table-cell px-3 sm:px-4 py-3 max-w-xs truncate text-sm text-slate-500" title="{{ $log->user_agent }}">
                                        {{ $log->user_agent ? \Illuminate\Support\Str::limit($log->user_agent, 60) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Certification footer for barangay record-keeping (print only). --}}
    <div class="print-only hidden mt-8 max-w-6xl mx-auto" aria-hidden="true">
        <p class="text-sm text-slate-700 leading-relaxed">
            I certify that this is a true and correct extract of the audit log of the
            Barangay Management System, covering the entries shown above
            @if (request()->filled('event') || request()->filled('search'))as filtered by the stated scope @endif
            as of {{ now()->format('F j, Y') }}.
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
