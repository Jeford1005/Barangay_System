{{-- Shared fields for household create/edit. Expects $household (null on create). --}}
@php
$residentOptions = $residents->mapWithKeys(fn ($r) => [$r->id => $r->first_name.' '.$r->last_name]);
@endphp

<fieldset>
    <legend class="text-sm font-semibold text-gray-900 mb-4">Household Details</legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="household_code" label="Household Code" required :value="$household->household_code ?? null" maxlength="20" placeholder="e.g. HH-004" />
        <x-form.field name="purok_id" label="Purok" type="select" optional-hint :options="$puroks" placeholder-option="Unassigned" :value="$household->purok_id ?? null" />
        <x-form.field name="house_type" label="House Type" type="select" required :options="['Single', 'Duplex', 'Apartment', 'Townhouse', 'Other']" :value="$household->house_type ?? 'Single'" />
        <x-form.field name="ownership" label="Ownership" type="select" required :options="['Owned', 'Rented', 'Leased', 'Occupied']" :value="$household->ownership ?? 'Owned'" />
        <x-form.field name="year_built" label="Year Built" type="number" step="1" :value="$household->year_built ?? null" min="1800" :max="date('Y')" placeholder="2020" />
        <x-form.field name="num_members" label="Number of Members" type="number" step="1" required :value="$household->num_members ?? 1" min="1" max="4294967295" placeholder="1" />
        <x-form.field name="lot_area" label="Lot Area" :value="$household->lot_area ?? null" maxlength="50" placeholder="e.g. 100 sqm" />
        <x-form.field name="floor_area" label="Floor Area" :value="$household->floor_area ?? null" maxlength="50" placeholder="e.g. 50 sqm" />
        <p class="text-xs text-neutral-500 sm:col-span-2">Household codes are text labels, such as HH-004. Choose a purok from the list, or leave it unassigned; its internal ID is saved automatically.</p>
        <x-form.field name="status" label="Status" type="select" required :options="['Occupied', 'Vacant', 'Under Construction']" :value="$household->status ?? 'Occupied'" />
    </div>
</fieldset>

<fieldset>
    <legend class="text-sm font-semibold text-gray-900 mb-4">Address</legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-form.field name="sitio" label="Sitio" :value="$household->sitio ?? null" maxlength="100" />
        <x-form.field name="street" label="Street" :value="$household->street ?? null" maxlength="150" />
        <x-form.field name="barangay" label="Barangay" :value="$household->barangay ?? null" maxlength="100" />
        <x-form.field name="municipality" label="Municipality" :value="$household->municipality ?? null" maxlength="100" />
        <x-form.field name="province" label="Province" :value="$household->province ?? null" maxlength="100" />
        <x-form.field name="region" label="Region" :value="$household->region ?? null" maxlength="50" />
        <x-form.field name="zip_code" label="Zip Code" inputmode="numeric" data-digits-only="true" pattern="[0-9]*" :value="$household->zip_code ?? null" minlength="4" maxlength="10" />
    </div>
</fieldset>

<fieldset>
    <legend class="text-sm font-semibold text-gray-900 mb-4">Head Assignment</legend>
    <x-form.field name="head_of_household_id" label="Head of Household" type="select" optional-hint :options="$residentOptions" placeholder-option="None" :value="$household->head_of_household_id ?? null" />
    <p class="mt-1 text-xs text-gray-500">Register residents first, then assign a head here.</p>
</fieldset>

<fieldset>
    <legend class="text-sm font-semibold text-gray-900 mb-4">Additional Information</legend>
    <x-form.field name="remarks" label="Remarks" type="textarea" :rows="3" maxlength="1000" :value="$household->remarks ?? null" />
</fieldset>
