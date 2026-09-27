@extends('layouts.app')

@section('title', 'Audit log')

@section('content')
    @php
        $actionTone = [
            'created' => 'bg-emerald-100 text-emerald-800',
            'approved' => 'bg-emerald-100 text-emerald-800',
            'restored' => 'bg-emerald-100 text-emerald-800',
            'updated' => 'bg-sky-100 text-sky-800',
            'login' => 'bg-sky-100 text-sky-800',
            'rejected' => 'bg-red-100 text-red-700',
            'deleted' => 'bg-red-100 text-red-700',
            'purged' => 'bg-red-100 text-red-700',
            'voided' => 'bg-red-100 text-red-700',
            'suspended' => 'bg-red-100 text-red-700',
            'exported' => 'bg-amber-100 text-amber-800',
        ];
        $hasFilters = $filters['search'] !== ''
            || $filters['action'] !== ''
            || $filters['entity_type'] !== ''
            || $filters['date'] !== '';
    @endphp

    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Audit log</h1>
            <p class="mt-1 text-sm text-slate-500">
                Every create, update, approval and export in the system is recorded here.
            </p>
        </div>

        <p class="text-sm text-slate-400">{{ number_format($logs->total()) }} entries</p>
    </div>

    <form method="GET" action="{{ route('admin.audit-logs.index') }}"
          class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="min-w-48 flex-1">
            <label for="audit_search" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
            <input id="audit_search"
                   type="search"
                   name="search"
                   value="{{ $filters['search'] }}"
                   maxlength="100"
                   placeholder="Action, entity, IP, user name or email"
                   autocomplete="off"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        </div>

        <div>
            <label for="audit_action" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Action</label>
            <select id="audit_action" name="action" class="mt-1.5 block w-44 rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">All actions</option>
                @foreach ($actions as $option)
                    <option value="{{ $option }}" @selected($filters['action'] === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="audit_entity" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Entity</label>
            <select id="audit_entity" name="entity_type" class="mt-1.5 block w-48 rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">All entities</option>
                @foreach ($entityTypes as $option)
                    <option value="{{ $option }}" @selected($filters['entity_type'] === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="audit_date" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Day</label>
            <input id="audit_date" type="date" name="date" value="{{ $filters['date'] }}"
                   class="mt-1.5 block w-44 rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                Apply
            </button>

            @if ($hasFilters)
                <a href="{{ route('admin.audit-logs.index') }}" class="text-sm text-slate-500 transition hover:text-slate-800">
                    Clear
                </a>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">When</th>
                    <th class="px-5 py-3">Who</th>
                    <th class="px-5 py-3">Action</th>
                    <th class="px-5 py-3">Entity</th>
                    <th class="px-5 py-3">IP</th>
                    <th class="px-5 py-3">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    @php($tone = $actionTone[$log->action] ?? 'bg-slate-100 text-slate-600')
                    <tr>
                        <td class="px-5 py-3.5 whitespace-nowrap text-slate-600">
                            {{ $log->created_at->format('M j, Y g:i A') }}
                        </td>

                        <td class="px-5 py-3.5">
                            @if ($log->user)
                                <p class="font-medium text-slate-800">{{ $log->user->name }}</p>
                                <p class="text-xs text-slate-500">{{ $log->user->email }}</p>
                            @else
                                <span class="text-slate-400">System</span>
                            @endif
                        </td>

                        <td class="px-5 py-3.5">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $tone }}">{{ $log->action }}</span>
                        </td>

                        <td class="px-5 py-3.5">
                            <p class="font-medium text-slate-700">{{ $log->entity_type }}</p>
                            @if ($log->entity_id !== null)
                                <p class="text-xs text-slate-400">ID #{{ $log->entity_id }}</p>
                            @endif
                        </td>

                        <td class="px-5 py-3.5 whitespace-nowrap text-slate-500">
                            {{ $log->ip_address ?? '—' }}
                        </td>

                        <td class="px-5 py-3.5">
                            @if ($log->before !== null || $log->after !== null)
                                <details class="text-xs text-slate-600">
                                    <summary class="cursor-pointer select-none font-medium text-slate-500 transition hover:text-slate-800">
                                        View changes
                                    </summary>

                                    <div class="mt-2 space-y-3">
                                        @if ($log->before !== null)
                                            <div>
                                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Before</p>
                                                <pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-100">{{ json_encode($log->before, JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                        @endif

                                        @if ($log->after !== null)
                                            <div>
                                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">After</p>
                                                <pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-100">{{ json_encode($log->after, JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                        @endif
                                    </div>
                                </details>
                            @else
                                <span class="text-xs text-slate-400">&mdash;</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">
                            @if ($hasFilters)
                                No audit entries match these filters.
                            @else
                                No audit entries yet &mdash; activity in the system will be recorded here.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $logs->links() }}
    </div>
@endsection
