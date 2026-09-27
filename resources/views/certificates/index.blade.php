<x-app-layout>
@section('page_header')
    <x-page-header title="Certificate Issuance" subtitle="{{ $todayCount }} issued today" />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow overflow-visible">

        <div class="p-6">
            <!-- Search + filters -->
            <div class="module-toolbar-sticky no-print mb-4 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <form method="GET" action="{{ route('certificates.index') }}" class="p-3">
                <div class="module-toolbar module-toolbar--three">
                    <div class="min-w-0">
                        <label for="certificate-search" class="sr-only">Search certificate issuances</label>
                        <input id="certificate-search" type="search" name="search" maxlength="100" value="{{ request('search') }}" placeholder="Search control no., resident, purpose"
                            class="min-h-10 min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="min-w-0">
                        <label for="certificate-type" class="sr-only">Filter by certificate type</label>
                        <select id="certificate-type" name="document_id" onchange="this.form.submit()" class="min-h-10 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All certificate types</option>
                            @foreach ($documents as $document)
                                <option value="{{ $document->id }}" @selected(request('document_id') == $document->id)>{{ $document->code }} — {{ $document->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label for="certificate-status" class="sr-only">Filter certificate issuances by status</label>
                        <select id="certificate-status" name="status" onchange="this.form.submit()" class="min-h-10 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Status</option>
                            @foreach (['Issued', 'Voided'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex min-w-0 items-center justify-end gap-2">
                        @if (auth()->user()?->isAdmin())
                         <a href="{{ route('admin.exports.certificates', request()->query()) }}" class="inline-flex min-h-10 items-center rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500"><x-icon name="arrow-down-tray" class="mr-1 h-4 w-4" /> Export</a>
                         @endif
                         <x-primary-action :href="route('certificates.create')" compact data-dialog-open="certificate-dialog">
                            <x-icon name="plus" class="h-4 w-4" />
                            Issue Certificate
                        </x-primary-action>
                    </div>
                </div>
            </form>
            </div>

            @if($issuances->isEmpty())
                <div class="text-center py-8 text-gray-500">
                    <x-icon name="document-text" class="mx-auto mb-4 h-12 w-12 text-gray-200" />
                    <p class="mt-2">No certificates issued yet</p>
                    <x-primary-action :href="route('certificates.create')" data-dialog-open="certificate-dialog" class="no-print mt-2">
                        Issue your first certificate
                    </x-primary-action>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Control No.</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificate</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resident</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="no-print px-3 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($issuances as $issuance)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="font-medium text-gray-900">{{ e($issuance->control_number) }}</span>
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-900">{{ e($issuance->document?->title) }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-900">{{ e($issuance->resident?->full_name) }}</span>
                                        @if ($issuance->resident?->purok)
                                            <span class="block text-xs text-gray-500">{{ e($issuance->resident->purok->name) }}</span>
                                        @endif
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4">
                                        <span class="text-sm text-gray-500">{{ e(Str::limit($issuance->purpose, 40)) }}</span>
                                    </td>
                                    <td class="hidden md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-500">{{ $issuance->created_at->format('M j, Y') }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        @if ($issuance->status === 'Voided')
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800">Voided</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800">Issued</span>
                                        @endif
                                    </td>
                                    <td class="no-print px-3 sm:px-6 py-2 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="inline-flex items-center gap-1">
                                            <a href="{{ route('certificates.print', $issuance) }}" class="inline-flex items-center justify-center min-h-9 min-w-9 rounded-md border border-blue-200 bg-white text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500" title="Print certificate">
                                                <x-icon name="printer" class="h-4 w-4" />
                                                <span class="sr-only">Print {{ e($issuance->control_number) }}</span>
                                            </a>
                                            @if (auth()->user()?->isAdmin() && $issuance->status === 'Issued')
                                                <form method="POST" action="{{ route('certificates.void', $issuance) }}" class="inline-flex"
                                                    onsubmit="return confirm('Void certificate {{ e($issuance->control_number) }}? This cannot be undone.');">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center justify-center min-h-9 min-w-9 rounded-md border border-red-200 bg-white text-red-600 hover:bg-red-50" title="Void certificate">
                                                        <x-icon name="x-mark" class="h-4 w-4" />
                                                        <span class="sr-only">Void {{ e($issuance->control_number) }}</span>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $issuances->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Issue-certificate dialog: success opens the print sheet in a new tab --}}
<x-crud-dialog id="certificate-dialog" title="Issue Certificate" description="Control number is assigned automatically on save" :fetch-base="route('certificates.create')" size="lg" data-open-on-success="true" />
@endsection
</x-app-layout>
