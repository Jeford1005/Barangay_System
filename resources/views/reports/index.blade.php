<x-app-layout>
@section('page_header')
    <x-page-header title="Reports" subtitle="Printable summaries for the barangay hall, city hall submissions, and planning." />
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <x-module-tabs type="reports" class="mb-4" />

    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <a href="{{ route('reports.population') }}" class="bg-white rounded-xl shadow p-6 hover:shadow-md transition-shadow flex flex-col">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-900">Population Report</h3>
                <x-icon name="residents" class="h-6 w-6 text-sky-600" />
            </div>
            <p class="mt-2 text-sm text-slate-500 flex-1">Active residents by purok with sex and age-bracket breakdown (children, minors, youth, adults, middle-aged, seniors), plus registered voters.</p>
            <span class="mt-4 text-sm font-medium text-sky-700">Open report →</span>
        </a>

        <a href="{{ route('reports.blotter') }}" class="bg-white rounded-xl shadow p-6 hover:shadow-md transition-shadow flex flex-col">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-900">Blotter Summary</h3>
                <x-icon name="clipboard-document-list" class="h-6 w-6 text-sky-600" />
            </div>
            <p class="mt-2 text-sm text-slate-500 flex-1">Case volume by complaint type and status for a period, with a monthly trend and recent cases appendix.</p>
            <span class="mt-4 text-sm font-medium text-sky-700">Open report →</span>
        </a>

        <a href="{{ route('reports.welfare') }}" class="bg-white rounded-xl shadow p-6 hover:shadow-md transition-shadow flex flex-col">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-900">Welfare Beneficiaries</h3>
                <x-icon name="shield-check" class="h-6 w-6 text-sky-600" />
            </div>
            <p class="mt-2 text-sm text-slate-500 flex-1">Assistance requests in a period with status counts, peso totals, and breakdowns by assistance type and program.</p>
            <span class="mt-4 text-sm font-medium text-sky-700">Open report →</span>
        </a>
    </div>

    <div class="mt-8 bg-white rounded-xl shadow p-6">
        <h3 class="text-sm font-semibold text-slate-900">About these reports</h3>
        <p class="mt-2 text-sm text-slate-500">
            Every report opens on screen with its filters; use <span class="font-medium text-slate-700">Print this report</span> to
            produce the official A4 document (or "Save as PDF" in the browser's print dialog for a PDF copy).
            Each printing is recorded in the audit log.
        </p>
    </div>
</div>
@endsection
</x-app-layout>
