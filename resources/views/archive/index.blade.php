<x-app-layout>
@section('page_header')
    <x-page-header title="Archive" subtitle="Deleted records are kept here for recovery. Restoring puts a record back exactly where it was; purging erases it permanently." />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow overflow-hidden">

        <div class="p-6">
            <!-- Tabs -->
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach ($types as $key => $meta)
                    <a href="{{ route('archive.type', $key) }}" @if($type === $key) aria-current="page" @endif
                        class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium border {{ $type === $key ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                        {{ $meta['label'] }}
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-xs font-bold {{ $type === $key ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600' }}">{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </div>

            <!-- Search -->
            <form method="GET" action="{{ route('archive.type', $type) }}" class="mb-4 flex flex-col gap-2 sm:flex-row">
                <label for="archive-search" class="sr-only">Search archived {{ strtolower($types[$type]['label']) }}</label>
                <input id="archive-search" type="search" name="search" maxlength="100" value="{{ request('search') }}" placeholder="Search archived {{ strtolower($types[$type]['label']) }}…"
                    class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="submit" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <x-icon name="funnel" class="mr-2 h-4 w-4 text-gray-500" />
                    Search
                </button>
                @if (request('search'))
                    <a href="{{ route('archive.type', $type) }}" class="inline-flex items-center justify-center rounded-md px-3 py-2 text-sm text-gray-500 hover:text-gray-800">Clear</a>
                @endif
            </form>

            @if($records->isEmpty())
                <div class="text-center py-8 text-gray-500">
                    <x-icon name="inbox" class="mx-auto mb-4 h-12 w-12 text-gray-200" />
                    <p class="mt-2">Nothing in the {{ strtolower($types[$type]['label']) }} archive{{ request('search') ? ' matching your search' : '' }}.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Record</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deleted</th>
                                <th class="no-print px-3 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($records as $record)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 sm:px-6 py-4">
                                        @if ($type === 'residents')
                                            <span class="font-medium text-gray-900">{{ e($record->full_name) }}</span>
                                            <span class="block text-xs text-gray-500">{{ e($record->sex) }}@if($record->birth_date) · {{ $record->birth_date->format('M j, Y') }}@endif</span>
                                        @elseif ($type === 'households')
                                            <span class="font-medium text-gray-900">{{ e($record->household_code) }}</span>
                                        @else
                                            <span class="font-medium text-gray-900">{{ e($record->case_number) }}</span>
                                        @endif
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4 text-sm text-gray-500">
                                        @if ($type === 'residents')
                                            {{ e($record->purok?->name ?? 'No purok') }}@if($record->address) · {{ e(Str::limit($record->address, 40)) }}@endif
                                        @elseif ($type === 'households')
                                            {{ e($record->purok?->name ?? 'No purok') }} · {{ $record->num_members }} member{{ $record->num_members === 1 ? '' : 's' }}
                                        @else
                                            {{ e(Str::limit($record->complaint_type, 50)) }}
                                        @endif
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $record->deleted_at->format('M j, Y') }}
                                        <span class="block text-xs text-gray-400">{{ $record->deleted_at->diffForHumans() }}</span>
                                    </td>
                                    <td class="no-print px-3 sm:px-6 py-2 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="inline-flex items-center gap-1">
                                            <form method="POST" action="{{ route('archive.restore', ['type' => $type, 'id' => $record->id]) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center justify-center min-h-9 rounded-md border border-green-200 bg-white px-3 text-sm font-medium text-green-700 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-500" title="Restore this record">
                                                    Restore
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('archive.destroy', ['type' => $type, 'id' => $record->id]) }}"
                                                onsubmit="return confirm('Permanently delete this {{ strtolower($types[$type]['singular']) }}? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center justify-center min-h-9 rounded-md border border-red-200 bg-white px-3 text-sm font-medium text-red-600 hover:bg-red-50" title="Delete permanently">
                                                    Delete forever
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
</x-app-layout>
