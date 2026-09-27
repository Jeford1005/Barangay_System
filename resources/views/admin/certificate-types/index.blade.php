<x-app-layout>
@section('page_header')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-page-header title="Certificate catalog" subtitle="Govern which document types residents and clerks may request or issue." />
        <a href="{{ route('admin.certificate-types.create') }}" class="inline-flex min-h-10 items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">New document type</a>
    </div>
@endsection

@section('content')
<div class="space-y-5">
    @if (session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>@endif

    <div class="module-toolbar-sticky rounded-xl border border-neutral-200 bg-white p-3 shadow-sm">
        <form method="GET" class="flex flex-col gap-2 sm:flex-row">
            <div class="min-w-0 flex-1"><label for="document-search" class="sr-only">Search document catalog</label><input id="document-search" name="search" value="{{ $search }}" maxlength="100" placeholder="Search code, title, or category" class="min-h-10 w-full rounded-lg border border-neutral-300 px-3 text-sm focus:border-blue-500 focus:ring-blue-500"></div>
            <div><label for="document-status" class="sr-only">Document status</label><select id="document-status" name="status" class="min-h-10 rounded-lg border border-neutral-300 bg-white px-3 text-sm"><option value="">All statuses</option>@foreach(['Active','Inactive','Draft'] as $option)<option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>@endforeach</select></div>
            <button class="min-h-10 rounded-lg border border-neutral-300 px-4 text-sm font-medium hover:bg-neutral-50" type="submit">Filter</button>
        </form>
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_20rem]">
        <div class="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-sm">
            <table class="min-w-[800px] divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-500"><tr><th class="px-4 py-3">Code</th><th class="px-4 py-3">Title</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Fee</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Issued</th><th class="no-print px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-neutral-100">
                @forelse($documents as $document)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-neutral-900">{{ $document->code }}</td>
                        <td class="px-4 py-3"><p class="font-medium text-neutral-900">{{ $document->title }}</p><p class="text-xs text-neutral-500">{{ $document->category }}</p></td>
                        <td class="px-4 py-3 text-neutral-700">{{ $document->document_type }}</td>
                        <td class="px-4 py-3 text-neutral-700">{{ (float) $document->fee > 0 ? '₱'.number_format((float) $document->fee, 2) : 'Free' }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium {{ $document->status === 'Active' ? 'bg-green-100 text-green-800' : ($document->status === 'Draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-neutral-100 text-neutral-700') }}">{{ $document->status }}</span></td>
                        <td class="px-4 py-3 text-neutral-600">{{ $document->issuances_count }}</td>
                        <td class="no-print px-4 py-3 text-right"><a href="{{ route('admin.certificate-types.edit', $document) }}" class="font-semibold text-green-700 underline hover:text-green-900">Edit</a></td>
                    </tr>
                @empty<tr><td colspan="7" class="px-4 py-10 text-center text-neutral-500">No document types match these filters.</td></tr>@endforelse
                </tbody>
            </table>
            <div class="border-t border-neutral-100 px-4 py-3">{{ $documents->links() }}</div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-neutral-900">Catalog rules</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-neutral-600"><li>Only Active Certificate/Clearance types are selectable for issuance.</li><li>Codes are limited to 2–8 letters or numbers.</li><li>Codes referenced by historical issuances cannot be renamed.</li><li>Referenced document types are never deleted; unused types are archived.</li></ul>
        </div>
    </div>
</div>
@endsection
</x-app-layout>
