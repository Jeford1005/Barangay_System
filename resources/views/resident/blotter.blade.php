<x-app-layout>
@section('page_header')
    <x-page-header title="Report Incident" subtitle="File a report with the barangay office and follow it here." />
@endsection

@section('content')
<div class="mx-auto max-w-4xl space-y-6">

    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">New report</h2>
        <p class="mt-1 text-sm text-slate-500">Your report is filed under your name as the complainant and enters the barangay blotter as an open case straight away.</p>

        <form method="POST" action="{{ route('resident.blotter.store') }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            @csrf
            <x-form.field name="complaint_type" label="Complaint type" required placeholder="e.g. Noise Complaint" maxlength="100" :value="old('complaint_type')" />
            <x-form.field name="complaint_date" label="Incident date" type="date" required :value="old('complaint_date')" />
            <div class="sm:col-span-2">
                <x-form.field name="accused_name" label="Person or party involved" required placeholder="Full name" maxlength="255" :value="old('accused_name')" />
            </div>
            <div class="sm:col-span-2">
                <x-form.field name="alleged_offense" label="What happened?" type="textarea" :rows="5" maxlength="2000" required :value="old('alleged_offense')" />
            </div>
            <div class="sm:col-span-2 flex justify-end">
                <button type="submit" class="btn btn-primary">Submit report</button>
            </div>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Reports</h2>
        </div>
        @if ($cases->isEmpty())
            <p class="p-6 text-sm text-slate-500">You have not filed any reports.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($cases as $case)
                    <li class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ $case->case_number }} · {{ $case->complaint_type }}</p>
                                <p class="mt-1 text-xs text-slate-500">Filed {{ $case->created_at->format('M j, Y g:i A') }} · incident on {{ $case->complaint_date->format('M j, Y') }}</p>
                                @if ($case->disposition)
                                    <p class="mt-2 text-sm text-slate-700">Office note: {{ $case->disposition }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium {{ ['Open' => 'bg-red-100 text-red-800', 'Pending' => 'bg-amber-100 text-amber-800', 'Resolved' => 'bg-emerald-100 text-emerald-800', 'Dismissed' => 'bg-slate-100 text-slate-700'][$case->status] ?? 'bg-slate-100 text-slate-700' }}">{{ $case->status }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="px-5 pb-4">{{ $cases->links() }}</div>
        @endif
    </section>
</div>
@endsection
</x-app-layout>
