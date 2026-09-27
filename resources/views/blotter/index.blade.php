<x-app-layout>
@section('page_header')
    <x-page-header title="Blotter Records" subtitle="{{ $openCount }} open {{ Str::plural('case', $openCount) }} awaiting action" />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow overflow-visible">

        <div class="p-6">
            <!-- Search + status filter -->
            <div class="module-toolbar-sticky no-print mb-4 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <form method="GET" action="{{ route('blotter.index') }}" class="p-3">
                <div class="module-toolbar module-toolbar--two">
                    <div class="min-w-0">
                        <label for="blotter-search" class="sr-only">Search blotter records</label>
                        <input id="blotter-search" type="search" name="search" maxlength="100" value="{{ request('search') }}" placeholder="Search case no., parties, complaint type"
                            class="min-h-10 min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="min-w-0">
                        <label for="blotter-status" class="sr-only">Filter blotter records by status</label>
                        <select id="blotter-status" name="status" onchange="this.form.submit()" class="min-h-10 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Status</option>
                            @foreach (['Open', 'Pending', 'Resolved', 'Dismissed'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex min-w-0 items-center justify-end gap-2">
                        @if (auth()->user()?->isAdmin())
                         <a href="{{ route('admin.exports.blotter', request()->query()) }}" class="inline-flex min-h-10 items-center rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500"><x-icon name="arrow-down-tray" class="mr-1 h-4 w-4" /> Export</a>
                         @endif
                         <x-primary-action :href="route('blotter.create')" compact data-dialog-open="blotter-dialog">
                            <x-icon name="plus" class="h-4 w-4" />
                            Record Case
                        </x-primary-action>
                    </div>
                </div>
            </form>
            </div>

            @if($blotters->isEmpty())
                <div class="text-center py-8 text-gray-500">
                    <x-icon name="document-text" class="mx-auto mb-4 h-12 w-12 text-gray-200" />
                    <p class="mt-2">No blotter cases found</p>
                    <x-primary-action :href="route('blotter.create')" data-dialog-open="blotter-dialog" class="no-print mt-2">
                        Record your first case
                    </x-primary-action>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case No.</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Complainant</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Respondent</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Complaint</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="no-print px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($blotters as $blotter)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="font-medium text-gray-900">{{ e($blotter->case_number) }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-900">{{ e($blotter->complainant_name) }}</span>
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-900">{{ e($blotter->accused_name) }}</span>
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4">
                                        <span class="text-sm text-gray-500">{{ e(Str::limit($blotter->complaint_type, 40)) }}</span>
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-500">{{ $blotter->complaint_date->format('M j, Y') }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        @php
                                            $badge = [
                                                'Open' => 'bg-red-100 text-red-800',
                                                'Pending' => 'bg-yellow-100 text-yellow-800',
                                                'Resolved' => 'bg-green-100 text-green-800',
                                                'Dismissed' => 'bg-gray-100 text-gray-600',
                                            ][$blotter->status] ?? 'bg-gray-100 text-gray-600';
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">
                                            {{ $blotter->status }}
                                        </span>
                                    </td>
                                    <td class="no-print px-3 sm:px-6 py-2 whitespace-nowrap text-right text-sm font-medium">
                                        <span class="inline-flex items-center justify-end gap-1 min-h-11">
                                            <a href="{{ route('blotter.edit', $blotter->id) }}" data-dialog-open="blotter-dialog" data-fetch-url="{{ route('blotter.edit', $blotter->id) }}" data-fetch-mode="edit"
                                                class="inline-flex items-center justify-center min-h-11 px-3 rounded-md text-sm font-medium text-green-700 hover:text-green-800 hover:bg-green-50 active:bg-green-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-600">
                                                Edit
                                            </a>
                                            <a href="{{ route('blotter.print', $blotter) }}" target="_blank" class="inline-flex items-center justify-center min-h-11 px-3 rounded-md text-sm font-medium text-blue-700 hover:text-blue-800 hover:bg-blue-50 active:bg-blue-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500" title="Print official case sheet">
                                                Print
                                            </a>
                                            <form action="{{ route('blotter.destroy', $blotter->id) }}" method="POST" class="inline {{ auth()->user()?->isStaff() ? 'hidden' : '' }}"
                                                onsubmit="return confirm('Delete case {{ e($blotter->case_number) }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center justify-center min-h-11 px-3 rounded-md text-sm font-medium text-red-600 hover:text-red-800 hover:bg-red-50 active:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 {{ auth()->user()?->isStaff() ? 'hidden' : '' }}">
                                                    Delete
                                                </button>
                                            </form>
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $blotters->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Create/edit blotter dialog --}}
<x-crud-dialog id="blotter-dialog" title="Blotter Case" description="Parties, incident, and case handling" :fetch-base="route('blotter.create')" size="lg" />
@endsection
</x-app-layout>
