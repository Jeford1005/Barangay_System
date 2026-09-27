{{-- Shared fields for blotter create/edit. Expects $blotter (null on create). --}}
@php
$residentOptions = $residents->mapWithKeys(fn ($r) => [$r->id => $r->last_name.', '.$r->first_name.($r->status === 'Active' && ! $r->deleted_at ? '' : ' [Archived]')]);
$officerOptions = $officials->mapWithKeys(fn ($o) => [$o->id => $o->last_name.', '.$o->first_name.' — '.$o->position]);
$residentAutofill = $residents->mapWithKeys(fn ($r) => [$r->id => [
    'name' => $r->full_name,
    'address' => $r->address,
    'phone' => $r->phone_number,
]]);
@endphp

<fieldset>
    <legend class="text-sm font-semibold text-gray-900 mb-4">Complainant</legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="complainant_name" label="Full Name" required :value="$blotter->complainant_name ?? null" maxlength="255" />
        <x-form.field name="complainant_id" label="Linked Resident" type="select" optional-hint :options="$residentOptions" placeholder-option="Walk-in / not registered" :value="$blotter->complainant_id ?? null" data-resident-autofill="complainant" data-linked-fields="complainant_name,complainant_address,complainant_phone" />
        <x-form.field name="complainant_address" label="Address" :value="$blotter->complainant_address ?? null" maxlength="255" />
        <x-form.field name="complainant_phone" label="Phone" type="tel" inputmode="tel" data-phone="true" pattern="[0-9+()\- ]*" :value="$blotter->complainant_phone ?? null" maxlength="15" placeholder="09171234567" />
    </div>
</fieldset>

<fieldset>
    <legend class="text-sm font-semibold text-gray-900 mb-4">Respondent (Accused)</legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="accused_name" label="Full Name" required :value="$blotter->accused_name ?? null" maxlength="255" />
        <x-form.field name="accused_id" label="Linked Resident" type="select" optional-hint :options="$residentOptions" placeholder-option="Walk-in / not registered" :value="$blotter->accused_id ?? null" data-resident-autofill="accused" data-linked-fields="accused_name,accused_address,accused_phone" />
        <x-form.field name="accused_address" label="Address" :value="$blotter->accused_address ?? null" maxlength="255" />
        <x-form.field name="accused_phone" label="Phone" type="tel" inputmode="tel" data-phone="true" pattern="[0-9+()\- ]*" :value="$blotter->accused_phone ?? null" maxlength="15" placeholder="09171234567" />
    </div>
</fieldset>

<fieldset>
    <legend class="text-sm font-semibold text-gray-900 mb-4">Incident</legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="complaint_type" label="Complaint Type" required :value="$blotter->complaint_type ?? null" maxlength="100" placeholder="e.g. Noise Complaint" />
        <x-form.field name="complaint_subtype" label="Subtype" :value="$blotter->complaint_subtype ?? null" maxlength="100" />
        <x-form.field name="complaint_date" label="Incident Date" type="date" required :value="$blotter?->complaint_date?->toDateString() ?? now()->toDateString()" :max="now()->toDateString()" />
        <x-form.field name="complaint_time" label="Incident Time" type="time" step="60" :value="$blotter?->complaint_time?->format('H:i') ?? null" />
        <div class="sm:col-span-2">
            <x-form.field name="alleged_offense" label="Alleged Offense / Narrative" type="textarea" :rows="4" maxlength="2000" required :value="$blotter->alleged_offense ?? null" />
        </div>
    </div>
</fieldset>

<fieldset>
    <legend class="text-sm font-semibold text-gray-900 mb-4">Status & Handling</legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="status" label="Status" type="select" required :options="['Open', 'Pending', 'Resolved', 'Dismissed']" :value="$blotter->status ?? 'Open'" />
        <x-form.field name="arrest_made" label="Arrest Made" type="select" required :options="['No', 'Yes']" :value="$blotter->arrest_made ?? 'No'" />
        <x-form.field name="investigator" label="Investigator" :value="$blotter->investigator ?? null" maxlength="255" />
        <x-form.field name="officer_id" label="Handling Officer" type="select" optional-hint :options="$officerOptions" placeholder-option="None" :value="$blotter->officer_id ?? null" />
        <div class="sm:col-span-2">
            <x-form.field name="disposition" label="Disposition" type="textarea" :rows="3" maxlength="2000" optional-hint :value="$blotter->disposition ?? null" />
            <p id="disposition-help" class="mt-1 hidden text-xs text-amber-700">A disposition and disposition date are required when the case is Resolved or Dismissed.</p>
        </div>
        <x-form.field name="disposition_date" label="Disposition Date" type="date" :value="$blotter?->disposition_date?->toDateString() ?? null" :max="now()->toDateString()" />
        <div class="sm:col-span-2">
            <x-form.field name="remarks" label="Remarks" type="textarea" :rows="2" maxlength="2000" :value="$blotter->remarks ?? null" />
        </div>
    </div>
</fieldset>

<script>
    (function () {
        const residentDetails = @json($residentAutofill);
        const bindings = {
            complainant: {
                name: 'complainant_name',
                address: 'complainant_address',
                phone: 'complainant_phone',
            },
            accused: {
                name: 'accused_name',
                address: 'accused_address',
                phone: 'accused_phone',
            },
        };

        document.querySelectorAll('[data-resident-autofill]').forEach((select) => {
            const binding = bindings[select.dataset.residentAutofill];
            if (!binding) return;

            const fields = Object.values(binding).map((id) => document.getElementById(id)).filter(Boolean);
            const apply = () => {
                const detail = residentDetails[select.value];
                const linked = Boolean(detail);
                if (detail) {
                    document.getElementById(binding.name).value = detail.name || '';
                    document.getElementById(binding.address).value = detail.address || '';
                    document.getElementById(binding.phone).value = detail.phone || '';
                }
                fields.forEach((field) => {
                    field.readOnly = linked;
                    field.classList.toggle('bg-neutral-100', linked);
                });
            };

            select.addEventListener('change', apply);
            apply();
        });
    })();
</script>

<script>
    (function () {
        const status = document.getElementById('status');
        const disposition = document.getElementById('disposition');
        const dispositionDate = document.getElementById('disposition_date');
        const help = document.getElementById('disposition-help');
        if (!status || !disposition || !dispositionDate || !help) return;

        const sync = () => {
            const required = ['Resolved', 'Dismissed'].includes(status.value);
            disposition.required = required;
            dispositionDate.required = required;
            help.classList.toggle('hidden', !required);
        };

        status.addEventListener('change', sync);
        sync();
    })();
</script>
