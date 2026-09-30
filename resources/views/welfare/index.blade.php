<x-app-layout>
@section('page_header')
    <x-page-header title="Social Welfare Assistance" subtitle="{{ $pendingCount }} new {{ Str::plural('request', $pendingCount) }} · ₱{{ number_format($pendingAmount, 2) }} approved awaiting release" />
@endsection

@section('content')
<div>
    <div class="bg-white rounded-xl shadow overflow-visible">

        <div class="p-6">
            <!-- Search + filters -->
            <div class="module-toolbar-sticky no-print mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <form method="GET" action="{{ route('welfare.index') }}" class="p-3">
                <div class="module-toolbar module-toolbar--three">
                    <div class="min-w-0">
                        <label for="welfare-search" class="sr-only">Search welfare requests</label>
                        <input id="welfare-search" type="search" name="search" maxlength="100" value="{{ request('search') }}" placeholder="Search beneficiary or program"
                            class="min-h-11 min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                    </div>
                    <div class="min-w-0">
                        <label for="welfare-type" class="sr-only">Filter welfare requests by assistance type</label>
                        <select id="welfare-type" name="assistance_type" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                            <option value="">All types</option>
                            @foreach (['Financial', 'Food', 'Medical', 'Educational', 'Housing', 'Other'] as $type)
                                <option value="{{ $type }}" @selected(request('assistance_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label for="welfare-status" class="sr-only">Filter welfare requests by status</label>
                        <select id="welfare-status" name="status" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                            <option value="">All Status</option>
                            @foreach (['Requested', 'Under Review', 'Approved', 'Denied', 'Released'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex min-w-0 items-center justify-end gap-2">
                        @if (auth()->user()?->isAdmin())
                         <a href="{{ route('admin.exports.welfare', request()->query()) }}" class="btn btn-outline"><x-icon name="arrow-down-tray" class="h-4 w-4" /> Export</a>
                         @endif
                        @if (auth()->user()?->hasPermission('welfare.intake'))
                         <x-primary-action :href="route('welfare.create')" compact data-dialog-open="welfare-dialog">
                            <x-icon name="plus" class="h-4 w-4" />
                            Record Request
                        </x-primary-action>
                        @endif
                    </div>
                </div>
            </form>
            </div>

            @if($welfares->isEmpty())
                <div class="text-center py-8 text-slate-500">
                    <x-icon name="document-text" class="mx-auto mb-4 h-12 w-12 text-slate-300" />
                    <p class="mt-2">No assistance requests found</p>
                    @if (auth()->user()?->hasPermission('welfare.intake'))
                    <x-primary-action :href="route('welfare.create')" data-dialog-open="welfare-dialog" class="no-print mt-2">
                        Record your first request
                    </x-primary-action>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Beneficiary</th>
                                <th class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Program</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Type</th>
                                <th class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Requested</th>
                                <th class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Approved</th>
                                <th class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Request Date</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="no-print px-3 sm:px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            @foreach($welfares as $welfare)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="font-medium text-slate-900">{{ e($welfare->beneficiary_name) }}</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4">
                                        <span class="text-sm text-slate-500">{{ e(Str::limit($welfare->program_name, 36)) }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-500">{{ $welfare->assistance_type }}</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-900">₱{{ number_format($welfare->requested_amount, 2) }}</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-900">₱{{ number_format((float) $welfare->approved_amount, 2) }}</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-500">{{ $welfare->request_date?->format('M j, Y') ?? '—' }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        @php
                                            $badge = [
                                                'Requested' => 'bg-slate-100 text-slate-700',
                                                'Under Review' => 'bg-amber-100 text-amber-800',
                                                'Approved' => 'bg-emerald-100 text-emerald-800',
                                                'Denied' => 'bg-red-100 text-red-800',
                                                'Released' => 'bg-sky-100 text-sky-800',
                                            ][$welfare->status] ?? 'bg-slate-100 text-slate-600';
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">
                                            {{ $welfare->status }}
                                        </span>
                                    </td>
                                    <td class="no-print px-3 sm:px-6 py-2 whitespace-nowrap text-right text-sm font-medium">
                                        <span class="inline-flex items-center justify-end gap-2">
                                            <a href="{{ route('welfare.edit', $welfare->id) }}" data-dialog-open="welfare-dialog" data-fetch-url="{{ route('welfare.edit', $welfare->id) }}" data-fetch-mode="edit"
                                                class="btn btn-neutral btn-row {{ auth()->user()?->hasPermission('welfare.approve') ? '' : 'hidden' }}">
                                                Edit
                                            </a>
                                            <form action="{{ route('welfare.destroy', $welfare->id) }}" method="POST" class="inline {{ auth()->user()?->isAdmin() ? '' : 'hidden' }}"
                                                data-confirm="Delete request for {{ $welfare->beneficiary_name }}?"
                                                data-confirm-title="Delete welfare request"
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
                    {{ $welfares->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Create/edit welfare dialog --}}
<x-crud-dialog id="welfare-dialog" title="Assistance Request" description="Beneficiary, assistance details, and status" :fetch-base="route('welfare.create')" size="lg" />
@endsection
</x-app-layout>
