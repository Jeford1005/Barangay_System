<x-app-layout>
@section('page_header')
    <x-page-header title="Cleanup Drives" subtitle="Community cleanups — scheduled, running, and finished" />
@endsection

@section('content')
<div>
    <div class="bg-white rounded-xl shadow overflow-visible">

        <div class="p-6">
            <!-- Search + status/purok filters -->
            <div class="module-toolbar-sticky no-print mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <form method="GET" action="{{ route('cleanup.index') }}" class="p-3">
                <div class="module-toolbar module-toolbar--three">
                    <div class="min-w-0">
                        <label for="cleanup-search" class="sr-only">Search cleanup drives</label>
                        <input id="cleanup-search" type="search" name="search" maxlength="100" value="{{ request('search') }}" placeholder="Search title, description"
                            class="min-h-11 min-w-0 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                    </div>
                    <div class="min-w-0">
                        <label for="cleanup-status" class="sr-only">Filter cleanup drives by status</label>
                        <select id="cleanup-status" name="status" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                            <option value="">All Status</option>
                            @foreach (\App\Models\CleanupDrive::STATUSES as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label for="cleanup-purok" class="sr-only">Filter cleanup drives by purok</label>
                        <select id="cleanup-purok" name="purok_id" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                            <option value="">All Puroks</option>
                            @foreach ($puroks as $id => $name)
                                <option value="{{ $id }}" @selected((string) request('purok_id') === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex min-w-0 flex-wrap items-center justify-end gap-2">
                        @if (auth()->user()?->isAdmin())
                         <a href="{{ route('cleanup.export', request()->query()) }}" class="btn btn-outline"><x-icon name="arrow-down-tray" class="h-4 w-4" /> Export</a>
                         @endif
                         @if (auth()->user()?->hasPermission('cleanup.manage'))
                         <x-primary-action :href="route('cleanup.create')" compact data-dialog-open="cleanup-dialog">
                            <x-icon name="plus" class="h-4 w-4" />
                            Schedule Drive
                        </x-primary-action>
                        @endif
                    </div>
                </div>
            </form>
            </div>

            @if($drives->isEmpty())
                <div class="text-center py-8 text-slate-500">
                    <x-icon name="clipboard-document-list" class="mx-auto mb-4 h-12 w-12 text-slate-200" />
                    <p class="mt-2">No cleanup drives found</p>
                    @if (auth()->user()?->hasPermission('cleanup.manage'))
                    <x-primary-action :href="route('cleanup.create')" data-dialog-open="cleanup-dialog" class="no-print mt-2">
                        Schedule your first drive
                    </x-primary-action>
                    @endif
                </div>
            @else
                <x-data-table>
                    <table class="min-w-full divide-y divide-slate-200">
                        <caption class="sr-only">Cleanup drive records</caption>
                        <thead class="sticky top-0 z-10 bg-slate-50">
                            <tr>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Title</th>
                                <th scope="col" class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Purok</th>
                                <th scope="col" class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Scheduled</th>
                                <th scope="col" class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Sign-ups</th>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="no-print px-3 sm:px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            @foreach($drives as $drive)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 sm:px-6 py-4">
                                        <span class="font-medium text-slate-900">{{ e($drive->title) }}</span>
                                        <span class="mt-1 block text-xs text-slate-500 md:hidden">{{ e($drive->purok?->name ?? 'All puroks') }} &middot; {{ $drive->scheduled_at?->format('M j, Y g:i A') ?? '—' }} &middot; {{ $drive->participants_count ?? 0 }} signed up</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-900">{{ e($drive->purok?->name ?? 'All puroks') }}</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-500">{{ $drive->scheduled_at?->format('M j, Y g:i A') ?? '—' }}</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-500">{{ $drive->participants_count ?? 0 }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        @php
                                            $badge = [
                                                'Scheduled' => 'bg-sky-100 text-sky-800',
                                                'Ongoing' => 'bg-amber-100 text-amber-800',
                                                'Completed' => 'bg-emerald-100 text-emerald-800',
                                                'Cancelled' => 'bg-slate-100 text-slate-600',
                                            ][$drive->status] ?? 'bg-slate-100 text-slate-600';
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">
                                            {{ $drive->status }}
                                        </span>
                                    </td>
                                    <td class="no-print px-3 sm:px-6 py-2 whitespace-nowrap text-right text-sm font-medium">
                                        <span class="inline-flex items-center justify-end gap-2">
                                            @if (Route::has('cleanup.logbook'))
                                                <a href="{{ route('cleanup.logbook', $drive) }}" target="_blank" class="btn btn-neutral btn-row" title="Print logbook sheet">
                                                    <x-icon name="printer" class="h-4 w-4" />
                                                    Logbook
                                                </a>
                                            @endif
                                            <a href="{{ route('cleanup.edit', $drive->id) }}" data-dialog-open="cleanup-dialog" data-fetch-url="{{ route('cleanup.edit', $drive->id) }}" data-fetch-mode="edit"
                                                class="btn btn-neutral btn-row {{ auth()->user()?->hasPermission('cleanup.manage') ? '' : 'hidden' }}">
                                                Edit
                                            </a>
                                            <form action="{{ route('cleanup.destroy', $drive->id) }}" method="POST" class="inline {{ auth()->user()?->isAdmin() ? '' : 'hidden' }}"
                                                data-confirm="Delete drive {{ $drive->title }}?"
                                                data-confirm-title="Delete drive"
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
                    {{ $drives->withQueryString()->links() }}</x-data-table>
            @endif
        </div>
    </div>
</div>

{{-- Create/edit cleanup dialog --}}
<x-crud-dialog id="cleanup-dialog" title="Cleanup Drive" description="Title, venue purok, schedule, and workflow status" :fetch-base="route('cleanup.create')" size="lg" />
@endsection
</x-app-layout>
