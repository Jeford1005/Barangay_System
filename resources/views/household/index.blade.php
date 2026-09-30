<x-app-layout>
@section('page_header')
    <x-page-header title="Household Management" />
@endsection

@section('content')
<div>
    <div class="bg-white rounded-xl shadow overflow-visible">
        <!-- Search + filters + create action -->
        <div class="module-toolbar-sticky no-print mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <form method="GET" action="{{ route('households.index') }}" class="p-3">
                <div class="module-toolbar module-toolbar--two">
                    <label for="household-search" class="sr-only">Search households</label>
                    <input id="household-search" name="search" type="search" maxlength="100" value="{{ request('search') }}" placeholder="Search code, street, or barangay" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                    <label for="household-purok" class="sr-only">Filter by purok</label>
                    <select id="household-purok" name="purok_id" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                        <option value="">All puroks</option>
                        @foreach ($puroks as $id => $name)
                            <option value="{{ $id }}" @selected(request('purok_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <div class="flex min-w-0 items-center justify-end gap-2">
                        @if (auth()->user()?->isAdmin())
                         <a href="{{ route('admin.exports.households', request()->query()) }}" class="btn btn-outline"><x-icon name="arrow-down-tray" class="h-4 w-4" /> Export</a>
                         @endif
                         @if (request()->filled('search') || request()->filled('purok_id'))
                            <a href="{{ route('households.index') }}" class="btn btn-neutral">Reset</a>
                        @endif
                        @if (auth()->user()?->hasPermission('households.manage'))
                        <x-primary-action :href="route('households.create')" compact data-dialog-open="household-dialog">
                            <x-icon name="plus" class="h-4 w-4" />
                            Add Household
                        </x-primary-action>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        <div class="p-6">
            @if($households->isEmpty())
                <div class="text-center py-8 text-slate-500">
                    <x-icon name="households" class="mx-auto mb-4 h-12 w-12 text-slate-200" />
                    <p class="mt-2">No households found</p>
                    @if (auth()->user()?->hasPermission('households.manage'))
                    <x-primary-action :href="route('households.create')" data-dialog-open="household-dialog" class="no-print mt-2">
                        Add your first household
                    </x-primary-action>
                    @endif
                </div>
            @else
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-slate-200">
                        <caption class="sr-only">Household records</caption>
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Household Code</th>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Purok</th>
                                <th scope="col" class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Head</th>
                                <th scope="col" class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Members</th>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="no-print px-3 sm:px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            @foreach($households as $household)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="font-medium text-slate-900">{{ e($household->household_code) }}</span>
                                        <span class="mt-1 block text-xs text-slate-500 md:hidden">Head: {{ $household->head ? $household->head->last_name.', '.$household->head->first_name : 'N/A' }} &middot; {{ $household->num_members }} member{{ (int) $household->num_members === 1 ? '' : 's' }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-500">{{ $household->purok ? $household->purok->name : 'N/A' }}</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-500">{{ $household->head ? $household->head->last_name . ', ' . $household->head->first_name : 'N/A' }}</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-500">{{ $household->num_members }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $household->status == 'Occupied' ? 'bg-emerald-100 text-emerald-800' : ($household->status == 'Vacant' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">
                                            {{ ucfirst($household->status) }}
                                        </span>
                                    </td>
                                    <td class="no-print px-3 sm:px-6 py-2 whitespace-nowrap text-right text-sm font-medium">
                                        <span class="inline-flex items-center justify-end gap-2">
                                            <a href="{{ route('households.edit', $household->id) }}" data-dialog-open="household-dialog" data-fetch-url="{{ route('households.edit', $household->id) }}" data-fetch-mode="edit"
                                                class="btn btn-neutral btn-row {{ auth()->user()?->hasPermission('households.manage') ? '' : 'hidden' }}">
                                                Edit
                                            </a>
                                            <form action="{{ route('households.destroy', $household->id) }}" method="POST" class="inline {{ auth()->user()?->isAdmin() ? '' : 'hidden' }}"
                                                data-confirm="Delete household {{ $household->household_code }}?"
                                                data-confirm-title="Delete household"
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
                    {{ $households->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Create/edit household dialog --}}
<x-crud-dialog id="household-dialog" title="Household Record" description="Household profile, address, and members" :fetch-base="route('households.create')" size="lg" />
@endsection
</x-app-layout>
