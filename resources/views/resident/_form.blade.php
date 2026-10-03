{{-- Shared fields for resident create/edit. Expects $resident (null on create). --}}
<fieldset class="border-b border-slate-200 pb-6 last:border-0 last:pb-0">
    <legend class="mb-4 text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Personal Information</legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-form.field name="first_name" label="First Name" required :value="$resident->first_name ?? null" maxlength="100" />
        <x-form.field name="middle_name" label="Middle Name" :value="$resident->middle_name ?? null" maxlength="100" />
        <x-form.field name="last_name" label="Last Name" required :value="$resident->last_name ?? null" maxlength="100" />
        <x-form.field name="suffix" label="Suffix" :value="$resident->suffix ?? null" maxlength="10" placeholder="Jr., III" />
        <x-form.field name="birth_date" label="Birth Date" type="date" :value="$resident?->birth_date?->toDateString()" :max="now()->toDateString()" />
        <x-form.field name="birthplace" label="Birthplace" :value="$resident->birthplace ?? null" maxlength="150" />
        <x-form.field name="sex" label="Sex" type="select" required :options="['Male', 'Female', 'Other']" :value="$resident->sex ?? null" />
        <x-form.field name="civil_status" label="Civil Status" type="select" required :options="['Single', 'Married', 'Divorced', 'Widowed', 'Separated']" :value="$resident->civil_status ?? 'Single'" />
        <x-form.field name="nationality" label="Nationality" :value="$resident->nationality ?? 'Filipino'" maxlength="50" />
        <x-form.field name="religion" label="Religion" :value="$resident->religion ?? null" maxlength="100" />
        <x-form.field name="education_level" label="Educational Attainment" :value="$resident->education_level ?? null" maxlength="100" />
        <x-form.field name="occupation" label="Occupation" :value="$resident->occupation ?? null" maxlength="100" />
        <x-form.field name="spouse_name" label="Spouse Name" :value="$resident->spouse_name ?? null" maxlength="100" />
        <x-form.field name="blood_type" label="Blood Type" :value="$resident->blood_type ?? null" maxlength="5" placeholder="O+" />
    </div>
</fieldset>

<fieldset class="border-b border-slate-200 pb-6 last:border-0 last:pb-0">
    <legend class="mb-4 text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Contact & Address</legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-form.field name="phone_number" label="Phone Number" type="tel" inputmode="numeric" data-phone="true" pattern="[0-9+()\- ]*" :value="$resident->phone_number ?? null" maxlength="15" placeholder="09XX XXX XXXX" />
        <x-form.field name="email" label="Email" type="email" :value="$resident->email ?? null" maxlength="150" />
        <div class="sm:col-span-2 lg:col-span-1">
            <x-form.field name="address" label="Address" :value="$resident->address ?? null" maxlength="255" />
        </div>
        <div>
            <label for="photo" class="mb-1.5 block text-sm font-medium text-slate-700">Photo <span class="text-slate-500">(optional)</span></label>
            @if ($resident?->photo)
                <img src="{{ route('residents.photo', $resident) }}" alt="Current resident photo" class="mb-2 h-16 w-16 rounded-full object-cover">
            @endif
            <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-file-max-kb="2048" class="block min-h-11 w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-sky-700 hover:file:bg-sky-100">
            @error('photo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
</fieldset>

<fieldset class="border-b border-slate-200 pb-6 last:border-0 last:pb-0">
    <legend class="mb-4 text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Location & Classification</legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-form.field name="purok_id" label="Purok" type="select" :options="$puroks" placeholder-option="None" :value="$resident->purok_id ?? null" />
        <x-form.field name="household_id" label="Household" type="select" :options="$households" placeholder-option="None" :value="$resident->household_id ?? null" />
        <p class="text-xs text-slate-500 sm:col-span-2 lg:col-span-4">Choose a purok or household from the list. Names and codes are shown for readability; the system stores the selected record ID. @if ($householdsCapped ?? false)Showing the first 1000 households — search the households list if the household is missing.@else{{ $households->count() }} household{{ $households->count() === 1 ? '' : 's' }} on the list.@endif</p>
        @php
            // Fixed options for new input; a legacy free-text value already
            // stored on this record is appended so it stays visible/selected.
            $residencyOptions = ['Permanent', 'Temporary', 'Transient'];
            $currentResidency = old('residency_status', $resident->residency_status ?? null);
            if ($currentResidency && ! in_array($currentResidency, $residencyOptions, true)) {
                $residencyOptions[] = $currentResidency;
            }
        @endphp
        <x-form.field name="residency_status" label="Residency Status" type="select" :options="$residencyOptions" placeholder-option="Select status" :value="$resident->residency_status ?? null" />
        @if (auth()->user()?->isAdmin())
         <x-form.field name="status" label="Status" type="select" required :options="['Active', 'Archived']" :value="$resident->status ?? 'Active'" />
         @else
         <input type="hidden" name="status" value="{{ $resident->status ?? 'Active' }}">
         <p class="text-xs text-slate-500">Status changes are administrator-only.</p>
         @endif
        <div class="flex min-w-0 flex-wrap items-center gap-x-8 gap-y-3 pt-1 sm:col-span-2 lg:col-span-4">
            <x-form.field name="voter_status" label="" type="checkbox" :checked="$resident->voter_status ?? false">
                Registered voter
            </x-form.field>
            <x-form.field name="is_household_head" label="" type="checkbox" :checked="$resident->is_household_head ?? false">
                Household head
            </x-form.field>
        </div>
    </div>
</fieldset>
