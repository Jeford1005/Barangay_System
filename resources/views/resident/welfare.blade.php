<x-app-layout>
@section('page_header')
    <x-page-header title="Request assistance" subtitle="Ask the barangay office for welfare assistance and follow it here." />
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
        <h2 class="text-lg font-semibold text-slate-900">New assistance request</h2>
        <p class="mt-1 text-sm text-slate-500">Your request enters the barangay welfare queue for review. Nothing is approved or released until staff decide it.</p>

        <form method="POST" action="{{ route('resident.welfare.store') }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            @csrf
            <x-form.field name="assistance_type" label="Assistance type" type="select" required :options="['Financial', 'Food', 'Medical', 'Educational', 'Housing', 'Other']" :value="old('assistance_type', 'Financial')" />
            <x-form.field name="requested_amount" label="Requested amount (₱)" type="number" required step="0.01" min="0" max="99999999.99" placeholder="1000.00" :value="old('requested_amount')" />
            <div class="sm:col-span-2">
                <x-form.field name="program_name" label="Program or assistance needed" required placeholder="e.g. Medical Assistance Program" maxlength="255" :value="old('program_name')" />
            </div>
            <div class="sm:col-span-2">
                <x-form.field name="remarks" label="Why you need this (optional)" type="textarea" :rows="3" maxlength="1000" :value="old('remarks')" />
            </div>
            <div class="sm:col-span-2 flex justify-end">
                <button type="submit" class="btn btn-primary">Submit request</button>
            </div>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Assistance requests</h2>
        </div>
        @if ($requests->isEmpty())
            <p class="p-6 text-sm text-slate-500">You have not requested assistance yet.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($requests as $entry)
                    <li class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ $entry->program_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $entry->assistance_type }} · ₱{{ number_format((float) $entry->requested_amount, 2) }} · requested {{ $entry->request_date->format('M j, Y') }}</p>
                                @if ($entry->remarks)
                                    <p class="mt-2 text-sm text-slate-700">{{ $entry->remarks }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium {{ ['Requested' => 'bg-slate-100 text-slate-700', 'Under Review' => 'bg-amber-100 text-amber-800', 'Approved' => 'bg-emerald-100 text-emerald-800', 'Denied' => 'bg-red-100 text-red-800', 'Released' => 'bg-sky-100 text-sky-800'][$entry->status] ?? 'bg-slate-100 text-slate-700' }}">{{ $entry->status }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="px-5 pb-4">{{ $requests->withQueryString()->links() }}</div>
        @endif
    </section>
</div>
@endsection
</x-app-layout>
