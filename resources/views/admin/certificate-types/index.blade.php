<x-app-layout>
@section('page_header')
    <x-page-header title="Certificate catalog" subtitle="Govern which document types residents and clerks may request or issue." />
@endsection

@section('content')
<div class="space-y-5">
    <x-module-tabs type="certificates" />
    @if (session('success'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>@endif

    <div class="module-toolbar-sticky rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <form method="GET" class="flex flex-col gap-2 sm:flex-row">
            <div class="min-w-0 flex-1"><label for="document-search" class="sr-only">Search document catalog</label><input id="document-search" name="search" value="{{ $search }}" maxlength="100" placeholder="Search code, title, or category" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-sky-600 focus:ring-sky-600"></div>
            <div><label for="document-status" class="sr-only">Document status</label><select id="document-status" name="status" onchange="this.form.submit()" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm"><option value="">All statuses</option>@foreach(['Active','Inactive','Draft'] as $option)<option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>@endforeach</select></div>
            <noscript><button class="btn btn-neutral" type="submit">Filter</button></noscript>
            <a href="{{ route('admin.certificate-types.create') }}" class="btn btn-primary">New document type</a>
        </form>
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_20rem]">
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-[800px] divide-y divide-slate-200 text-sm">
                <caption class="sr-only">Certificate document catalog</caption>
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th scope="col" class="px-4 py-3">Code</th><th scope="col" class="px-4 py-3">Title</th><th scope="col" class="px-4 py-3">Type</th><th scope="col" class="px-4 py-3">Fee</th><th scope="col" class="px-4 py-3">Status</th><th scope="col" class="px-4 py-3">Issued</th><th scope="col" class="no-print px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($documents as $document)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $document->code }}</td>
                        <td class="px-4 py-3"><p class="font-medium text-slate-900">{{ $document->title }}</p><p class="text-xs text-slate-500">{{ $document->category }}</p></td>
                        <td class="px-4 py-3 text-slate-700">{{ $document->document_type }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ (float) $document->fee > 0 ? '₱'.number_format((float) $document->fee, 2) : 'Free' }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium {{ $document->status === 'Active' ? 'bg-emerald-100 text-emerald-800' : ($document->status === 'Draft' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">{{ $document->status }}</span></td>
                        <td class="px-4 py-3 text-slate-600">{{ $document->issuances_count }}</td>
                        <td class="no-print px-4 py-3 text-right">
                            <span class="inline-flex items-center justify-end gap-2">
                                <a href="{{ route('admin.certificate-types.edit', $document) }}" class="btn btn-neutral btn-row">Edit</a>
                                @if (auth()->user()?->isAdmin())
                                    <form method="POST" action="{{ route('admin.certificate-types.destroy', $document) }}" class="inline"
                                        data-confirm="Delete document type {{ $document->code }}? Unused types are archived; types with history are kept."
                                        data-confirm-title="Delete document type"
                                        data-confirm-accept="Delete" data-confirm-icon="trash">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-row">Delete</button>
                                    </form>
                                @endif
                            </span>
                        </td>
                    </tr>
                @empty<tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No document types match these filters.</td></tr>@endforelse
                </tbody>
            </table>
            <div class="border-t border-slate-200 px-4 py-3">{{ $documents->withQueryString()->links() }}</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Catalog rules</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-600"><li>Only Active Certificate/Clearance types are selectable for issuance.</li><li>Codes are limited to 2–8 letters or numbers.</li><li>Codes referenced by historical issuances cannot be renamed.</li><li>Referenced document types are never deleted; unused types are archived.</li></ul>
        </div>
    </div>
</div>
@endsection
</x-app-layout>
