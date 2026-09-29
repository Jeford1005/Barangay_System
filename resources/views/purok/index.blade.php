<x-app-layout>
@section('page_header')
    <x-page-header title="Purok Management" />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow overflow-visible">
        <!-- Search + filter + create action -->
        <div class="module-toolbar-sticky no-print mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <form method="GET" action="{{ route('puroks.index') }}" class="p-3">
                <div class="module-toolbar module-toolbar--one">
                    <label for="purok-search" class="sr-only">Search puroks</label>
                    <input id="purok-search" name="search" type="search" maxlength="100" value="{{ request('search') }}" placeholder="Search purok name or code" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                    <div class="flex min-w-0 items-center justify-end gap-2">
                        @if (request()->filled('search'))
                            <a href="{{ route('puroks.index') }}" class="inline-flex min-h-11 items-center rounded-lg px-2 text-sm font-medium text-slate-500 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-600">Reset</a>
                        @endif
                        @if (auth()->user()?->isAdmin())
                            <x-primary-action :href="route('puroks.create')" compact data-dialog-open="purok-dialog">
                                <x-icon name="plus" class="h-4 w-4" />
                                Add Purok
                            </x-primary-action>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        <div class="p-6">
            @if($puroks->isEmpty())
                <div class="text-center py-8 text-slate-500">
                    <x-icon name="map-pin" class="mx-auto mb-4 h-12 w-12 text-slate-200" />
                    <p class="mt-2">No puroks found</p>
                    @if (auth()->user()?->isAdmin())
                        <x-primary-action :href="route('puroks.create')" data-dialog-open="purok-dialog" class="no-print mt-2">
                            Add your first purok
                        </x-primary-action>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Purok Name</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Code</th>
                                <th class="no-print px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            @foreach($puroks as $purok)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="font-medium text-slate-900">{{ e($purok->name) }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-500">{{ e($purok->code) }}</span>
                                    </td>
                                    <td class="no-print px-3 sm:px-6 py-2 whitespace-nowrap text-right text-sm font-medium">
                                        @if (auth()->user()?->isAdmin())
                                            <span class="inline-flex items-center justify-end gap-1 min-h-11">
                                                <a href="{{ route('puroks.edit', $purok->id) }}" data-dialog-open="purok-dialog" data-fetch-url="{{ route('puroks.edit', $purok->id) }}" data-fetch-mode="edit"
                                                    class="inline-flex items-center justify-center min-h-11 px-3 rounded-md text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 active:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                                                    Edit
                                                </a>
                                                <form action="{{ route('puroks.destroy', $purok->id) }}" method="POST" class="inline"
                                                    data-confirm="Delete {{ $purok->name }}?"
                                                    data-confirm-title="Delete purok"
                                                    data-confirm-accept="Delete">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center justify-center min-h-11 px-3 rounded-md text-sm font-medium text-red-600 hover:text-red-800 hover:bg-red-50 active:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                                        Delete
                                                    </button>
                                                </form>
                                            </span>
                                        @else
                                            <span class="text-xs text-slate-500">View only</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $puroks->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Create/edit purok dialog --}}
@if (auth()->user()?->isAdmin())
    <x-crud-dialog id="purok-dialog" title="Purok" description="Purok name and code" :fetch-base="route('puroks.create')" size="md" />
@endif
@endsection
</x-app-layout>
