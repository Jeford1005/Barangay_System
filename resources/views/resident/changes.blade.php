<x-app-layout>
@section('page_header')
    <x-page-header title="Request a profile correction" subtitle="Ask the barangay office to review a change to your resident record." />
@endsection

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <a href="{{ route('resident.portal') }}" class="text-sm text-blue-700 hover:text-blue-900">← Back to my profile</a>
        <h1 class="mt-3 text-2xl font-bold text-neutral-900">Request a profile correction</h1>
        <p class="mt-1 max-w-2xl text-sm text-neutral-600">Changes to official profile details require barangay staff review. Your current record stays unchanged until a staff member approves the request.</p>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-neutral-900">New correction request</h2>
        <form method="POST" action="{{ route('resident.changes.store') }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            @csrf
            <x-form.field name="occupation" label="Occupation" :value="old('occupation', $resident->occupation)" maxlength="100" />
            <x-form.field name="religion" label="Religion" :value="old('religion', $resident->religion)" maxlength="100" />
            <x-form.field name="residency_status" label="Residency status" :value="old('residency_status', $resident->residency_status)" maxlength="50" />
            <x-form.field name="purok_id" label="Purok" type="select" :options="$puroks" :value="old('purok_id', $resident->purok_id)" optional-hint />
            <x-form.field name="household_id" label="Household code" type="select" :options="$households" :value="old('household_id', $resident->household_id)" optional-hint />
            <div class="sm:col-span-2">
                <x-form.field name="notes" label="Notes for staff (optional)" type="textarea" :rows="3" maxlength="1000" :value="old('notes')" />
            </div>
            <div class="sm:col-span-2 flex justify-end">
                <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">Submit correction request</button>
            </div>
        </form>
    </section>

    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm">
        <div class="border-b border-neutral-100 px-5 py-4"><h2 class="text-lg font-semibold text-neutral-900">My requests</h2></div>
        @if ($changes->isEmpty())
            <p class="p-6 text-sm text-neutral-500">You have no correction requests.</p>
        @else
            <ul class="divide-y divide-neutral-100">
                @foreach ($changes as $change)
                    <li class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-neutral-900">{{ implode(', ', array_map(fn ($field) => str_replace('_', ' ', $field), array_keys($change->changes))) }}</p>
                                <p class="mt-1 text-xs text-neutral-500">Submitted {{ $change->created_at->format('M j, Y g:i A') }}</p>
                                @if ($change->review_note)<p class="mt-2 text-sm text-neutral-700">Staff note: {{ $change->review_note }}</p>@endif
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ ['Pending' => 'bg-yellow-100 text-yellow-800', 'Approved' => 'bg-green-100 text-green-800', 'Rejected' => 'bg-red-100 text-red-800', 'Cancelled' => 'bg-neutral-100 text-neutral-700'][$change->status] }}">{{ $change->status }}</span>
                                @if ($change->status === 'Pending')
                                    <form method="POST" action="{{ route('resident.changes.cancel', $change) }}" onsubmit="return confirm('Cancel this request?');">
                                        @csrf
                                        <button class="text-xs font-semibold text-red-700 underline hover:text-red-900" type="submit">Cancel</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="px-5 pb-4">{{ $changes->links() }}</div>
        @endif
    </section>
</div>
@endsection
</x-app-layout>
