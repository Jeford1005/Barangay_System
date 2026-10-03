{{-- Shared fields for welfare create/edit. Expects $welfare (null on create). --}}
@php
$residentOptions = $residents->mapWithKeys(fn ($r) => [$r->id => $r->last_name.', '.$r->first_name.($r->status === 'Active' && ! $r->deleted_at ? '' : ' [Archived]')]);
$residentAutofill = $residents->mapWithKeys(fn ($r) => [$r->id => [
    'name' => $r->full_name,
    'address' => $r->address,
    'phone' => $r->phone_number,
]]);
@endphp

<fieldset>
    <legend class="text-sm font-semibold text-slate-900 mb-4">Beneficiary</legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="beneficiary_name" label="Full Name" required :value="$welfare->beneficiary_name ?? null" maxlength="255" />
        <x-form.field name="beneficiary_id" label="Linked Resident" type="select" optional-hint :options="$residentOptions" placeholder-option="Walk-in / not registered" :value="$welfare->beneficiary_id ?? null" data-resident-autofill="beneficiary" data-linked-fields="beneficiary_name,beneficiary_address,beneficiary_phone" />
        <p class="mt-1 text-xs text-slate-500">@if ($residents->count() >= 1000)Showing the first 1000 residents — search the residents list if the beneficiary is missing.@else{{ $residents->count() }} resident{{ $residents->count() === 1 ? '' : 's' }} on the list.@endif</p>
        <x-form.field name="beneficiary_address" label="Address" :value="$welfare->beneficiary_address ?? null" maxlength="255" />
        <x-form.field name="beneficiary_phone" label="Phone" type="tel" inputmode="numeric" data-phone="true" pattern="[0-9+()\- ]*" :value="$welfare->beneficiary_phone ?? null" maxlength="15" placeholder="09XX XXX XXXX" />
    </div>
</fieldset>

<fieldset>
    <legend class="text-sm font-semibold text-slate-900 mb-4">Assistance</legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="assistance_type" label="Assistance Type" type="select" required :options="['Financial', 'Food', 'Medical', 'Educational', 'Housing', 'Other']" :value="$welfare->assistance_type ?? 'Financial'" />
        <x-form.field name="program_name" label="Program Name" required :value="$welfare->program_name ?? null" maxlength="255" placeholder="e.g. Medical Assistance Program" />
        <div class="sm:col-span-2">
            <x-form.field name="program_description" label="Program Description" type="textarea" :rows="3" maxlength="2000" :value="$welfare->program_description ?? null" />
        </div>
        <x-form.field name="requested_amount" label="Requested Amount (₱)" type="number" required :value="$welfare->requested_amount ?? null" step="0.01" min="0" max="99999999.99" placeholder="1000.00" />
        @if (auth()->user()?->hasPermission('welfare.approve'))
         <x-form.field name="approved_amount" label="Approved Amount (₱)" type="number" :value="$welfare->approved_amount ?? null" step="0.01" min="0" max="99999999.99" placeholder="1000.00" optional-hint />
         @endif
        <x-form.field name="request_date" label="Request Date" type="date" required :value="$welfare?->request_date?->toDateString() ?? now()->toDateString()" :max="now()->toDateString()" />
        @if (auth()->user()?->hasPermission('welfare.approve'))
         <x-form.field name="status" label="Status" type="select" required :options="['Requested', 'Under Review', 'Approved', 'Denied', 'Released']" :value="$welfare->status ?? 'Requested'" />
        <x-form.field name="approval_date" label="Approval Date" type="date" :value="$welfare?->approval_date?->toDateString() ?? null" :max="now()->toDateString()" optional-hint />
        <x-form.field name="release_date" label="Release Date" type="date" :value="$welfare?->release_date?->toDateString() ?? null" :max="now()->toDateString()" optional-hint />
        <p id="welfare-workflow-help" class="hidden text-xs text-amber-700 sm:col-span-2">Approved or Released requests require an approval date and a positive approved amount. Released requests also require a release date.</p>
         @else
             <input type="hidden" name="status" value="Requested">
             <input type="hidden" name="approved_amount" value="0">
             <p class="text-xs text-slate-500 sm:col-span-2">Approval, release, and denial decisions are reserved for administrators and barangay officials.</p>
         @endif
        <div class="sm:col-span-2">
            <x-form.field name="remarks" label="Remarks" type="textarea" :rows="2" maxlength="2000" :value="$welfare->remarks ?? null" />
        </div>
    </div>
</fieldset>

<script>
    (function () {
        const residentDetails = @json($residentAutofill);
        const select = document.getElementById('beneficiary_id');
        if (!select) return;

        const fields = ['beneficiary_name', 'beneficiary_address', 'beneficiary_phone']
            .map((id) => document.getElementById(id))
            .filter(Boolean);

        const apply = () => {
            const detail = residentDetails[select.value];
            const linked = Boolean(detail);
            if (detail) {
                document.getElementById('beneficiary_name').value = detail.name || '';
                document.getElementById('beneficiary_address').value = detail.address || '';
                document.getElementById('beneficiary_phone').value = detail.phone || '';
            }
            fields.forEach((field) => {
                field.readOnly = linked;
                field.classList.toggle('bg-slate-100', linked);
            });
        };

        select.addEventListener('change', apply);
        apply();
    })();
</script>

<script>
    (function () {
        const status = document.getElementById('status');
        const approvalDate = document.getElementById('approval_date');
        const approvedAmount = document.getElementById('approved_amount');
        const releaseDate = document.getElementById('release_date');
        const help = document.getElementById('welfare-workflow-help');
        if (!status || !approvalDate || !approvedAmount || !releaseDate || !help) return;

        const sync = () => {
            const approved = status.value === 'Approved';
            const released = status.value === 'Released';
            approvalDate.required = approved || released;
            approvedAmount.required = approved || released;
            releaseDate.required = released;
            help.classList.toggle('hidden', !approved && !released);
        };

        status.addEventListener('change', sync);
        sync();
    })();
</script>
