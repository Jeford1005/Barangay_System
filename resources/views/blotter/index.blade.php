<x-app-layout>
@section('page_header')
    <x-page-header title="Blotter Records" subtitle="{{ $openCount }} open {{ Str::plural('case', $openCount) }} awaiting action" />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow overflow-visible">

        <div class="p-6">
            <!-- Search + status filter -->
            <div class="module-toolbar-sticky no-print mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <form method="GET" action="{{ route('blotter.index') }}" class="p-3">
                <div class="module-toolbar module-toolbar--two">
                    <div class="min-w-0">
                        <label for="blotter-search" class="sr-only">Search blotter records</label>
                        <input id="blotter-search" type="search" name="search" maxlength="100" value="{{ request('search') }}" placeholder="Search case no., parties, complaint type"
                            class="min-h-11 min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                    </div>
                    <div class="min-w-0">
                        <label for="blotter-status" class="sr-only">Filter blotter records by status</label>
                        <select id="blotter-status" name="status" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                            <option value="">All Status</option>
                            @foreach (['Open', 'Pending', 'Resolved', 'Dismissed'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex min-w-0 items-center justify-end gap-2">
                        @if (auth()->user()?->isAdmin())
                         <a href="{{ route('admin.exports.blotter', request()->query()) }}" class="btn btn-outline"><x-icon name="arrow-down-tray" class="h-4 w-4" /> Export</a>
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
                <div class="text-center py-8 text-slate-500">
                    <x-icon name="document-text" class="mx-auto mb-4 h-12 w-12 text-slate-200" />
                    <p class="mt-2">No blotter cases found</p>
                    <x-primary-action :href="route('blotter.create')" data-dialog-open="blotter-dialog" class="no-print mt-2">
                        Record your first case
                    </x-primary-action>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Case No.</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Complainant</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Respondent</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Complaint</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Date</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="no-print px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            @foreach($blotters as $blotter)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="font-medium text-slate-900">{{ e($blotter->case_number) }}</span>
                                        @if ($blotter->reported_by_resident)
                                            <span class="no-print ml-1 inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800">Resident-reported</span>
                                        @endif
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-900">{{ e($blotter->complainant_name) }}</span>
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-900">{{ e($blotter->accused_name) }}</span>
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4">
                                        <span class="text-sm text-slate-500">{{ e(Str::limit($blotter->complaint_type, 40)) }}</span>
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-500">{{ $blotter->complaint_date->format('M j, Y') }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        @php
                                            $badge = [
                                                'Open' => 'bg-red-100 text-red-800',
                                                'Pending' => 'bg-amber-100 text-amber-800',
                                                'Resolved' => 'bg-emerald-100 text-emerald-800',
                                                'Dismissed' => 'bg-slate-100 text-slate-600',
                                            ][$blotter->status] ?? 'bg-slate-100 text-slate-600';
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">
                                            {{ $blotter->status }}
                                        </span>
                                    </td>
                                    <td class="no-print px-3 sm:px-6 py-2 whitespace-nowrap text-right text-sm font-medium">
                                        <span class="inline-flex items-center justify-end gap-2">
                                            <a href="{{ route('blotter.edit', $blotter->id) }}" data-dialog-open="blotter-dialog" data-fetch-url="{{ route('blotter.edit', $blotter->id) }}" data-fetch-mode="edit"
                                                class="btn btn-neutral btn-row">
                                                Edit
                                            </a>
                                            <a href="{{ route('blotter.print', $blotter) }}" target="_blank" class="btn btn-neutral btn-row" title="Print official case sheet">
                                                Print
                                            </a>
                                            <form action="{{ route('blotter.destroy', $blotter->id) }}" method="POST" class="inline {{ auth()->user()?->isAdmin() ? '' : 'hidden' }}"
                                                data-confirm="Delete case {{ $blotter->case_number }}?"
                                                data-confirm-title="Delete case"
                                                data-confirm-accept="Delete" data-confirm-icon="trash">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-row {{ auth()->user()?->isAdmin() ? '' : 'hidden' }}">
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
