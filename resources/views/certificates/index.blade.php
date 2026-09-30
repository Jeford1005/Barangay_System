<x-app-layout>
@section('page_header')
    <x-page-header title="Certificate Issuance" subtitle="{{ $todayCount }} issued today" />
@endsection

@section('content')
<div>
    <x-module-tabs type="certificates" class="mb-4" />
    <div class="bg-white rounded-xl shadow overflow-visible">

        <div class="p-6">
            <!-- Search + filters -->
            <div class="module-toolbar-sticky no-print mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <form method="GET" action="{{ route('certificates.index') }}" class="p-3">
                <div class="module-toolbar module-toolbar--three">
                    <div class="min-w-0">
                        <label for="certificate-search" class="sr-only">Search certificate issuances</label>
                        <input id="certificate-search" type="search" name="search" maxlength="100" value="{{ request('search') }}" placeholder="Search control no., resident, purpose"
                            class="min-h-11 min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                    </div>
                    <div class="min-w-0">
                        <label for="certificate-type" class="sr-only">Filter by certificate type</label>
                        <select id="certificate-type" name="document_id" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                            <option value="">All certificate types</option>
                            @foreach ($documents as $document)
                                <option value="{{ $document->id }}" @selected(request('document_id') == $document->id)>{{ $document->code }} — {{ $document->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label for="certificate-status" class="sr-only">Filter certificate issuances by status</label>
                        <select id="certificate-status" name="status" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent">
                            <option value="">All Status</option>
                            @foreach (['Issued', 'Voided'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex min-w-0 items-center justify-end gap-2">
                        @if (auth()->user()?->isAdmin())
                         <a href="{{ route('admin.exports.certificates', request()->query()) }}" class="btn btn-outline"><x-icon name="arrow-down-tray" class="h-4 w-4" /> Export</a>
                         @endif
                        @if (auth()->user()?->hasPermission('certificates.issue'))
                         <x-primary-action :href="route('certificates.create')" compact data-dialog-open="certificate-dialog">
                            <x-icon name="plus" class="h-4 w-4" />
                            Issue Certificate
                        </x-primary-action>
                        @endif
                    </div>
                </div>
            </form>
            </div>

            @if($issuances->isEmpty())
                <div class="text-center py-8 text-slate-500">
                    <x-icon name="document-text" class="mx-auto mb-4 h-12 w-12 text-slate-200" />
                    <p class="mt-2">No certificates issued yet</p>
                    @if (auth()->user()?->hasPermission('certificates.issue'))
                    <x-primary-action :href="route('certificates.create')" data-dialog-open="certificate-dialog" class="no-print mt-2">
                        Issue your first certificate
                    </x-primary-action>
                    @endif
                </div>
            @else
                <x-data-table>
                    <table class="min-w-full divide-y divide-slate-200">
                        <caption class="sr-only">Issued certificates</caption>
                        <thead class="sticky top-0 z-10 bg-slate-50">
                            <tr>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Control No.</th>
                                <th scope="col" class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Certificate</th>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Resident</th>
                                <th scope="col" class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Purpose</th>
                                <th scope="col" class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="no-print px-3 sm:px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            @foreach($issuances as $issuance)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="font-medium text-slate-900">{{ e($issuance->control_number) }}</span>
                                        <span class="mt-1 block text-xs text-slate-500 md:hidden">{{ e($issuance->document?->title ?? '—') }} &middot; {{ e($issuance->purpose ?? '—') }} &middot; {{ $issuance->created_at?->format('M j, Y') ?? '—' }}</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-900">{{ e($issuance->document?->title) }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-900">{{ e($issuance->resident?->full_name) }}</span>
                                        @if ($issuance->resident?->purok)
                                            <span class="block text-xs text-slate-500">{{ e($issuance->resident->purok->name) }}</span>
                                        @endif
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4">
                                        <span class="text-sm text-slate-500">{{ e(Str::limit($issuance->purpose, 40)) }}</span>
                                    </td>
                                    <td class="hidden print:table-cell md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-500">{{ $issuance->created_at->format('M j, Y') }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        @if ($issuance->status === 'Voided')
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800">Voided</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800">Issued</span>
                                        @endif
                                    </td>
                                    <td class="no-print px-3 sm:px-6 py-2 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="inline-flex items-center gap-2">
                                            @if ($issuance->status === 'Voided')
                                                @if (auth()->user()?->isAdmin())
                                                    <form method="POST" action="{{ route('certificates.restore', $issuance) }}" class="inline-flex"
                                                        data-confirm="Restore certificate {{ $issuance->control_number }}? It will become printable again."
                                                        data-confirm-title="Restore certificate"
                                                        data-confirm-accept="Restore" data-confirm-tone="primary" data-confirm-icon="check-circle">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-success btn-icon" title="Restore certificate">
                                                            <x-icon name="check-circle" class="h-4 w-4" />
                                                            <span class="sr-only">Restore {{ e($issuance->control_number) }}</span>
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-xs text-slate-500">Voided</span>
                                                @endif
                                            @else
                                                <a href="{{ route('certificates.print', $issuance) }}" class="btn btn-outline btn-row {{ auth()->user()?->hasPermission('certificates.issue') ? '' : 'hidden' }}" title="Print certificate">
                                                    <x-icon name="printer" class="h-4 w-4" />
                                                    Print
                                                    <span class="sr-only">{{ e($issuance->control_number) }}</span>
                                                </a>
                                                @if (auth()->user()?->isAdmin())
                                                    <form method="POST" action="{{ route('certificates.void', $issuance) }}" class="inline-flex"
                                                        data-confirm="Void certificate {{ $issuance->control_number }}? It will no longer print until an administrator restores it."
                                                        data-confirm-title="Void certificate"
                                                        data-confirm-accept="Void" data-confirm-icon="x-circle">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-danger btn-icon" title="Void certificate">
                                                            <x-icon name="x-mark" class="h-4 w-4" />
                                                            <span class="sr-only">Void {{ e($issuance->control_number) }}</span>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $issuances->withQueryString()->links() }}</x-data-table>
            @endif
        </div>
    </div>
</div>

{{-- Issue-certificate dialog: success opens the print sheet in a new tab --}}
<x-crud-dialog id="certificate-dialog" title="Issue Certificate" description="Control number is assigned automatically on save" :fetch-base="route('certificates.create')" size="lg" data-open-on-success="true" />
@endsection
</x-app-layout>
