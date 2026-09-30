<x-app-layout>
@section('page_header')
    <x-page-header title="Resident Management" subtitle="{{ $residents->total() }} resident record{{ $residents->total() === 1 ? '' : 's' }}" />
@endsection

@section('content')
<div class="space-y-4">
    <x-module-tabs type="residents" />
    <div class="module-toolbar-sticky module-toolbar-sticky--bare no-print">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" action="{{ route('residents.index') }}" class="p-3">
            <div class="module-toolbar module-toolbar--resident">
                <div class="min-w-0">
                    <label for="resident-search" class="sr-only">Search residents</label>
                    <input id="resident-search" name="search" type="search" maxlength="100" value="{{ request('search') }}" placeholder="Search by name" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                </div>
                <div class="min-w-0">
                    <label for="resident-purok" class="sr-only">Filter by purok</label>
                    <select id="resident-purok" name="purok_id" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                        <option value="">All puroks</option>
                        @foreach ($puroks as $id => $name)
                            <option value="{{ $id }}" @selected(request('purok_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0">
                    <label for="resident-household" class="sr-only">Filter by household</label>
                    <select id="resident-household" name="household_id" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                        <option value="">All households</option>
                        @foreach ($households as $id => $code)
                            <option value="{{ $id }}" @selected(request('household_id') == $id)>{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0">
                    <label for="resident-status" class="sr-only">Filter by status</label>
                    <select id="resident-status" name="status" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                        <option value="">All statuses</option>
                        <option value="Active" @selected(request('status') === 'Active')>Active</option>
                        <option value="Archived" @selected(request('status') === 'Archived')>Archived</option>
                    </select>
                </div>
                <noscript><button type="submit" class="btn btn-neutral">Filter</button></noscript>
                <div class="flex min-w-0 items-center justify-end gap-2">
                    @if (auth()->user()?->isAdmin())
                     <a href="{{ route('admin.exports.residents', request()->query()) }}" class="btn btn-outline">
                         <x-icon name="arrow-down-tray" class="h-4 w-4" /> Export
                     </a>
                     @endif
                     @if (request()->filled('search') || request()->filled('purok_id') || request()->filled('household_id') || request()->filled('status'))
                        <a href="{{ route('residents.index') }}" class="btn btn-neutral">Reset</a>
                    @endif
                    @if (auth()->user()?->hasPermission('residents.manage'))
                    <x-primary-action :href="route('residents.create')" compact data-dialog-open="resident-dialog">
                        <x-icon name="plus" class="h-4 w-4" />
                        Add Resident
                    </x-primary-action>
                    @endif
                </div>
            </div>
        </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($residents->isEmpty())
            <div class="px-6 py-14 text-center">
                <x-icon name="residents" class="mx-auto h-10 w-10 text-slate-300" />
                <h2 class="mt-3 text-sm font-semibold text-slate-900">No residents found</h2>
                <p class="mt-1 text-sm text-slate-500">Try clearing the filters or add the first resident record.</p>
                @if (auth()->user()?->hasPermission('residents.manage'))
                <x-primary-action :href="route('residents.create')" data-dialog-open="resident-dialog" class="no-print mt-5">
                    <x-icon name="plus" class="h-4 w-4" /> Add Resident
                </x-primary-action>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <caption class="sr-only">Resident records</caption>
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="hidden px-4 py-3 font-medium print:table-cell md:table-cell">#</th>
                            <th scope="col" class="px-4 py-3 font-medium">Name</th>
                            <th scope="col" class="hidden px-4 py-3 font-medium print:table-cell md:table-cell">Sex</th>
                            <th scope="col" class="hidden px-4 py-3 font-medium print:table-cell md:table-cell">Civil status</th>
                            <th scope="col" class="hidden px-4 py-3 font-medium print:table-cell md:table-cell">Birth date</th>
                            <th scope="col" class="hidden px-4 py-3 font-medium print:table-cell md:table-cell">Contact</th>
                            <th scope="col" class="px-4 py-3 font-medium">Purok</th>
                            <th scope="col" class="hidden px-4 py-3 font-medium print:table-cell md:table-cell">Household</th>
                            <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            <th scope="col" class="no-print px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($residents as $resident)
                            @php
                                $isActive = $resident->status === 'Active';
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="hidden whitespace-nowrap px-4 py-3 text-slate-500 print:table-cell md:table-cell">{{ ($residents->firstItem() ?? 0) + $loop->iteration - 1 }}</td>
                                <td class="min-w-[180px] px-4 py-3">
                                    <span class="font-medium text-slate-900">{{ $resident->last_name }}, {{ $resident->first_name }} {{ $resident->suffix }}</span>
                                    <span class="mt-1 block text-xs text-slate-500 md:hidden">{{ $resident->phone_number ?? '—' }} &middot; {{ $resident->household?->household_code ?? '—' }}</span>
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-slate-600 print:table-cell md:table-cell">{{ $resident->sex }}</td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-slate-600 print:table-cell md:table-cell">{{ $resident->civil_status }}</td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-slate-600 print:table-cell md:table-cell">{{ $resident->birth_date?->format('M j, Y') ?? '—' }}</td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-slate-600 print:table-cell md:table-cell">{{ $resident->phone_number ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $resident->purok?->name ?? '—' }}</td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-slate-600 print:table-cell md:table-cell">{{ $resident->household?->household_code ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ $resident->status }}</span>
                                </td>
                                <td class="no-print whitespace-nowrap px-4 py-3 text-right">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        <a href="{{ route('residents.edit', $resident->id) }}" data-dialog-open="resident-dialog" data-fetch-url="{{ route('residents.edit', $resident->id) }}" data-fetch-mode="edit" class="btn btn-neutral btn-row {{ auth()->user()?->hasPermission('residents.manage') ? '' : 'hidden' }}">Edit</a>
                                        @if (auth()->user()?->isAdmin() && $isActive)
                                            <form method="POST" action="{{ route('residents.archive', $resident->id) }}" data-confirm="Archive this resident? Their portal access will be blocked." data-confirm-title="Archive resident" data-confirm-accept="Archive" data-confirm-icon="archive-box">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-warning btn-row {{ auth()->user()?->isStaff() ? 'hidden' : '' }}">Archive</button>
                                            </form>
                                        @elseif (auth()->user()?->isAdmin())
                                            <form method="POST" action="{{ route('residents.restore', $resident->id) }}" data-confirm="Restore this resident? They will return to the active list." data-confirm-title="Restore resident" data-confirm-accept="Restore" data-confirm-tone="primary" data-confirm-icon="archive-box">
                                                @csrf
                                                <button type="submit" class="btn btn-outline btn-row {{ auth()->user()?->isStaff() ? 'hidden' : '' }}">Restore</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $residents->withQueryString()->links() }}</div>
        @endif
    </div>
</div>

<x-crud-dialog id="resident-dialog" title="Resident Record" description="Personal, contact, and location details" :fetch-base="route('residents.create')" size="full" />
@endsection
</x-app-layout>
