<x-app-layout>
@section('page_header')
    <x-page-header title="Social Welfare Assistance" subtitle="{{ $pendingCount }} new {{ Str::plural('request', $pendingCount) }} · ₱{{ number_format($pendingAmount, 2) }} approved awaiting release" />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow overflow-visible">

        <div class="p-6">
            <!-- Search + filters -->
            <div class="module-toolbar-sticky no-print mb-4 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <form method="GET" action="{{ route('welfare.index') }}" class="p-3">
                <div class="module-toolbar module-toolbar--three">
                    <div class="min-w-0">
                        <label for="welfare-search" class="sr-only">Search welfare requests</label>
                        <input id="welfare-search" type="search" name="search" maxlength="100" value="{{ request('search') }}" placeholder="Search beneficiary or program"
                            class="min-h-10 min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="min-w-0">
                        <label for="welfare-type" class="sr-only">Filter welfare requests by assistance type</label>
                        <select id="welfare-type" name="assistance_type" onchange="this.form.submit()" class="min-h-10 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All types</option>
                            @foreach (['Financial', 'Food', 'Medical', 'Educational', 'Housing', 'Other'] as $type)
                                <option value="{{ $type }}" @selected(request('assistance_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label for="welfare-status" class="sr-only">Filter welfare requests by status</label>
                        <select id="welfare-status" name="status" onchange="this.form.submit()" class="min-h-10 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Status</option>
                            @foreach (['Requested', 'Under Review', 'Approved', 'Denied', 'Released'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex min-w-0 items-center justify-end gap-2">
                        @if (auth()->user()?->isAdmin())
                         <a href="{{ route('admin.exports.welfare', request()->query()) }}" class="inline-flex min-h-10 items-center rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500"><x-icon name="arrow-down-tray" class="mr-1 h-4 w-4" /> Export</a>
                         @endif
                         <x-primary-action :href="route('welfare.create')" compact data-dialog-open="welfare-dialog">
                            <x-icon name="plus" class="h-4 w-4" />
                            Record Request
                        </x-primary-action>
                    </div>
                </div>
            </form>
            </div>

            @if($welfares->isEmpty())
                <div class="text-center py-8 text-gray-500">
                    <x-icon name="document-text" class="mx-auto mb-4 h-12 w-12 text-gray-300" />
                    <p class="mt-2">No assistance requests found</p>
                    <x-primary-action :href="route('welfare.create')" data-dialog-open="welfare-dialog" class="no-print mt-2">
                        Record your first request
                    </x-primary-action>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Beneficiary</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Date</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="no-print px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($welfares as $welfare)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="font-medium text-gray-900">{{ e($welfare->beneficiary_name) }}</span>
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4">
                                        <span class="text-sm text-gray-500">{{ e(Str::limit($welfare->program_name, 36)) }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-500">{{ $welfare->assistance_type }}</span>
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-900">₱{{ number_format($welfare->requested_amount, 2) }}</span>
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-900">{{ $welfare->approved_amount > 0 ? '₱'.number_format($welfare->approved_amount, 2) : '—' }}</span>
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-500">{{ $welfare->request_date->format('M j, Y') }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        @php
                                            $badge = [
                                                'Requested' => 'bg-gray-100 text-gray-700',
                                                'Under Review' => 'bg-yellow-100 text-yellow-800',
                                                'Approved' => 'bg-green-100 text-green-800',
                                                'Denied' => 'bg-red-100 text-red-800',
                                                'Released' => 'bg-blue-100 text-blue-800',
                                            ][$welfare->status] ?? 'bg-gray-100 text-gray-600';
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">
                                            {{ $welfare->status }}
                                        </span>
                                    </td>
                                    <td class="no-print px-3 sm:px-6 py-2 whitespace-nowrap text-right text-sm font-medium">
                                        <span class="inline-flex items-center justify-end gap-1 min-h-11">
                                            <a href="{{ route('welfare.edit', $welfare->id) }}" data-dialog-open="welfare-dialog" data-fetch-url="{{ route('welfare.edit', $welfare->id) }}" data-fetch-mode="edit"
                                                class="inline-flex items-center justify-center min-h-11 px-3 rounded-md text-sm font-medium text-green-700 hover:text-green-800 hover:bg-green-50 active:bg-green-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-600 {{ auth()->user()?->isStaff() ? 'hidden' : '' }}">
                                                Edit
                                            </a>
                                            <form action="{{ route('welfare.destroy', $welfare->id) }}" method="POST" class="inline {{ auth()->user()?->isStaff() ? 'hidden' : '' }}"
                                                onsubmit="return confirm('Delete request for {{ e($welfare->beneficiary_name) }}?');">
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
                    {{ $welfares->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Create/edit welfare dialog --}}
<x-crud-dialog id="welfare-dialog" title="Assistance Request" description="Beneficiary, assistance details, and status" :fetch-base="route('welfare.create')" size="lg" />
@endsection
</x-app-layout>
