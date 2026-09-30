<x-app-layout>
@section('page_header')
    <x-page-header title="Archive" subtitle="Deleted records are kept here for recovery. Restoring puts a record back exactly where it was; purging erases it permanently." />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow overflow-hidden">

        <div class="p-6">
            <!-- Tabs: single scroll-row on phones (module-tabs pattern), wrap on sm+ -->
            <div class="flex flex-nowrap gap-2 mb-4 overflow-x-auto pb-1 sm:flex-wrap sm:overflow-visible sm:pb-0">
                @foreach ($types as $key => $meta)
                    <a href="{{ route('archive.type', $key) }}" @if($type === $key) aria-current="page" @endif
                        class="inline-flex min-h-11 items-center gap-2 rounded-full px-4 py-2 text-sm font-medium border {{ $type === $key ? 'bg-sky-600 text-white border-sky-600' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                        {{ $meta['label'] }}
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-xs font-bold {{ $type === $key ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </div>

            <!-- Search: input + button share one row at every width -->
            <form method="GET" action="{{ route('archive.type', $type) }}" class="mb-4 flex flex-row flex-wrap gap-2">
                <label for="archive-search" class="sr-only">Search archived {{ strtolower($types[$type]['label']) }}</label>
                <input id="archive-search" type="search" name="search" maxlength="100" value="{{ request('search') }}" placeholder="Search archived {{ strtolower($types[$type]['label']) }}…"
                    class="flex-1 min-w-0 min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                <button type="submit" class="btn btn-neutral">
                    <x-icon name="funnel" class="h-4 w-4 text-slate-500" />
                    Search
                </button>
                @if (request('search'))
                    <a href="{{ route('archive.type', $type) }}" class="btn btn-ghost">Clear</a>
                @endif
            </form>

            @if($records->isEmpty())
                <div class="text-center py-8 text-slate-500">
                    <x-icon name="inbox" class="mx-auto mb-4 h-12 w-12 text-slate-200" />
                    <h2 class="mt-2 text-sm font-semibold text-slate-900">No archived {{ strtolower($types[$type]['label']) }} found</h2>
                    <p class="mt-1 text-sm">{{ request('search') ? 'Nothing matches your search. Try different keywords or clear the search.' : 'Deleted records will appear here for recovery.' }}</p>
                    @if (request('search'))
                        <a href="{{ route('archive.type', $type) }}" class="btn btn-neutral mt-4">Clear search</a>
                    @else
                        <a href="{{ route($type.'.index') }}" class="btn btn-neutral mt-4">Browse active {{ strtolower($types[$type]['label']) }}</a>
                    @endif
                </div>
            @else
                <x-data-table>
                    <table class="min-w-full divide-y divide-slate-200">
                        <caption class="sr-only">Archived records awaiting restore or permanent deletion</caption>
                        <thead class="sticky top-0 z-10 bg-slate-50">
                            <tr>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Record</th>
                                <th scope="col" class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Details</th>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Deleted</th>
                                <th scope="col" class="no-print px-3 sm:px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            @foreach($records as $record)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 sm:px-6 py-4">
                                        @if ($type === 'residents')
                                            <span class="font-medium text-slate-900">{{ e($record->full_name) }}</span>
                                            <span class="block text-xs text-slate-500">{{ e($record->sex) }}@if($record->birth_date) · {{ $record->birth_date->format('M j, Y') }}@endif</span>
                                        @elseif ($type === 'households')
                                            <span class="font-medium text-slate-900">{{ e($record->household_code) }}</span>
                                        @elseif ($type === 'blotter')
                                            <span class="font-medium text-slate-900">{{ e($record->case_number) }}</span>
                                        @else
                                            <span class="font-medium text-slate-900">{{ e($record->beneficiary_name) }}</span>
                                            <span class="block text-xs text-slate-500">{{ e($record->assistance_type) }} · ₱{{ number_format((float) $record->approved_amount, 2) }}</span>
                                        @endif
                                        <span class="mt-1 block text-xs text-slate-500 md:hidden">@if ($type === 'residents'){{ e($record->purok?->name ?? 'No purok') }}@if($record->address) &middot; {{ e(Str::limit($record->address, 40)) }}@endif@elseif ($type === 'households'){{ e($record->purok?->name ?? 'No purok') }} &middot; {{ $record->num_members ?? '—' }} member{{ (int) ($record->num_members ?? 0) === 1 ? '' : 's' }}@elseif ($type === 'blotter'){{ e($record->complaint_type ?? '—') }}@else{{ e($record->program_name ?? '—') }} &middot; {{ e($record->status ?? '—') }}@endif</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4 text-sm text-slate-500">
                                        @if ($type === 'residents')
                                            {{ e($record->purok?->name ?? 'No purok') }}@if($record->address) · {{ e(Str::limit($record->address, 40)) }}@endif
                                        @elseif ($type === 'households')
                                            {{ e($record->purok?->name ?? 'No purok') }} · {{ $record->num_members }} member{{ $record->num_members === 1 ? '' : 's' }}
                                        @elseif ($type === 'blotter')
                                            {{ e(Str::limit($record->complaint_type, 50)) }}
                                        @else
                                            {{ e(Str::limit($record->program_name, 50)) }} · {{ e($record->status) }}
                                        @endif
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                        {{ $record->deleted_at->format('M j, Y') }}
                                        <span class="block text-xs text-slate-500">{{ $record->deleted_at->diffForHumans() }}</span>
                                    </td>
                                    <td class="no-print px-3 sm:px-6 py-2 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="inline-flex items-center gap-2">
                                            <form method="POST" action="{{ route('archive.restore', ['type' => $type, 'id' => $record->id]) }}"
                                                data-confirm="Restore this {{ strtolower($types[$type]['singular']) }}? It will return to the active list."
                                                data-confirm-title="Restore {{ strtolower($types[$type]['singular']) }}"
                                                data-confirm-accept="Restore" data-confirm-tone="primary" data-confirm-icon="archive-box">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success btn-row" title="Restore this record">
                                                    Restore
                                                </button>
                                            </form>
                                            @if ($type !== 'welfare')
                                            <form method="POST" action="{{ route('archive.destroy', ['type' => $type, 'id' => $record->id]) }}"
                                                data-confirm="Permanently delete this {{ strtolower($types[$type]['singular']) }}? This cannot be undone."
                                                data-confirm-title="Delete {{ strtolower($types[$type]['singular']) }}"
                                                data-confirm-accept="Delete" data-confirm-icon="trash">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-row" title="Delete permanently">
                                                    Delete
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $records->withQueryString()->links() }}</x-data-table>
            @endif
        </div>
    </div>
</div>
@endsection
</x-app-layout>
