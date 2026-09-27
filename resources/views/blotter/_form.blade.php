{{--
    Shared blotter form — used by create and edit.

    Expects: $blotter (null on create), $puroks (Purok collection),
             $officers (name => name options for the handling officer).
--}}
<form method="POST"
      action="{{ $blotter?->exists ? route('blotter.update', $blotter) : route('blotter.store') }}"
      data-submit-loading
      data-loading-label="Saving…"
      class="space-y-8">
    @csrf
    @if ($blotter?->exists)
        @method('PUT')
    @endif

    {{-- ── incident details ── --}}
    <section class="grid gap-5 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <h2 class="text-sm font-semibold text-slate-900">Incident details</h2>
            <p class="mt-0.5 text-xs text-slate-500">What happened, when and where.</p>
        </div>

        <div>
            <label for="incident_date" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Incident date *</label>
            <input id="incident_date"
                   type="date"
                   name="incident_date"
                   value="{{ old('incident_date', $blotter?->incident_date?->format('Y-m-d')) }}"
                   max="{{ now()->toDateString() }}"
                   required
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('incident_date') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
            @error('incident_date')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="incident_time" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Incident time</label>
            <input id="incident_time"
                   type="time"
                   name="incident_time"
                   value="{{ old('incident_time', $blotter?->incident_time?->format('H:i')) }}"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('incident_time') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
            <p class="mt-1 text-xs text-slate-400">Leave blank if the exact time is unknown.</p>
            @error('incident_time')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="incident_type" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Incident type *</label>
            <input id="incident_type"
                   type="text"
                   name="incident_type"
                   value="{{ old('incident_type', $blotter?->incident_type) }}"
                   maxlength="100"
                   required
                   placeholder="e.g. Theft, Physical injury, Noise complaint"
                   autocomplete="off"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('incident_type') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
            @error('incident_type')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="purok_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Purok</label>
            <select id="purok_id"
                    name="purok_id"
                    class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('purok_id') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                <option value="">— Not applicable / unknown —</option>
                @foreach ($puroks as $purok)
                    <option value="{{ $purok->id }}" @selected((string) old('purok_id', $blotter?->purok_id) === (string) $purok->id)>
                        {{ $purok->label() }}
                    </option>
                @endforeach
            </select>
            @error('purok_id')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="location" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Location *</label>
            <input id="location"
                   type="text"
                   name="location"
                   value="{{ old('location', $blotter?->location) }}"
                   maxlength="255"
                   required
                   placeholder="Street, landmark or sitio"
                   autocomplete="off"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('location') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
            @error('location')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </section>

    {{-- ── parties ── --}}
    <section class="grid gap-5 border-t border-slate-100 pt-6 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <h2 class="text-sm font-semibold text-slate-900">Parties involved</h2>
            <p class="mt-0.5 text-xs text-slate-500">Contacts must contain at least one digit, e.g. 0917 123 4567.</p>
        </div>

        <div>
            <label for="complainant_name" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Complainant name *</label>
            <input id="complainant_name"
                   type="text"
                   name="complainant_name"
                   value="{{ old('complainant_name', $blotter?->complainant_name) }}"
                   maxlength="150"
                   required
                   autocomplete="off"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('complainant_name') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
            @error('complainant_name')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="complainant_contact" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Complainant contact</label>
            <input id="complainant_contact"
                   type="text"
                   name="complainant_contact"
                   value="{{ old('complainant_contact', $blotter?->complainant_contact) }}"
                   maxlength="30"
                   placeholder="0917 123 4567"
                   autocomplete="off"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('complainant_contact') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
            @error('complainant_contact')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="respondent_name" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Respondent name</label>
            <input id="respondent_name"
                   type="text"
                   name="respondent_name"
                   value="{{ old('respondent_name', $blotter?->respondent_name) }}"
                   maxlength="150"
                   placeholder="Leave blank if unknown"
                   autocomplete="off"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('respondent_name') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
            @error('respondent_name')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="respondent_contact" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Respondent contact</label>
            <input id="respondent_contact"
                   type="text"
                   name="respondent_contact"
                   value="{{ old('respondent_contact', $blotter?->respondent_contact) }}"
                   maxlength="30"
                   placeholder="0917 123 4567"
                   autocomplete="off"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('respondent_contact') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
            @error('respondent_contact')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </section>

    {{-- ── narrative ── --}}
    <section class="grid gap-5 border-t border-slate-100 pt-6 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <h2 class="text-sm font-semibold text-slate-900">Narrative</h2>
            <p class="mt-0.5 text-xs text-slate-500">Plain account of what was reported, up to 5,000 characters.</p>
        </div>

        <div class="sm:col-span-2">
            <label for="narrative" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">What happened? *</label>
            <textarea id="narrative"
                      name="narrative"
                      rows="8"
                      maxlength="5000"
                      required
                      placeholder="Describe the incident in chronological order…"
                      class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('narrative') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">{{ old('narrative', $blotter?->narrative) }}</textarea>
            @error('narrative')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </section>

    {{-- ── case handling ── --}}
    <section class="grid gap-5 border-t border-slate-100 pt-6 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <h2 class="text-sm font-semibold text-slate-900">Case handling</h2>
            <p class="mt-0.5 text-xs text-slate-500">Officer, arrest, current status and resolution.</p>
        </div>

        <div>
            <label for="handling_officer" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Handling officer</label>
            <select id="handling_officer"
                    name="handling_officer"
                    class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('handling_officer') border-red-400 focus:border-red-400/20 @enderror">
                <option value="">— Not yet assigned —</option>
                @foreach ($officers as $officerName)
                    <option value="{{ $officerName }}" @selected(old('handling_officer', $blotter?->handling_officer) === $officerName)>
                        {{ $officerName }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">Active barangay officials; the name is stored on the case sheet.</p>
            @error('handling_officer')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="arrest_made" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Arrest made? *</label>
            <select id="arrest_made"
                    name="arrest_made"
                    required
                    class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('arrest_made') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @foreach (\App\Models\Blotter::ARREST_OPTIONS as $option)
                    <option value="{{ $option }}" @selected(old('arrest_made', $blotter?->arrest_made ?? 'No') === $option)>{{ $option }}</option>
                @endforeach
            </select>
            @error('arrest_made')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status *</label>
            <select id="status"
                    name="status"
                    required
                    class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('status') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @foreach (\App\Models\Blotter::STATUSES as $option)
                    <option value="{{ $option }}" @selected(old('status', $blotter?->status ?? 'Open') === $option)>{{ $option }}</option>
                @endforeach
            </select>
            @error('status')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="resolution_notes" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Resolution notes</label>
            <textarea id="resolution_notes"
                      name="resolution_notes"
                      rows="4"
                      maxlength="5000"
                      placeholder="How the case was settled or dismissed…"
                      class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('resolution_notes') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">{{ old('resolution_notes', $blotter?->resolution_notes) }}</textarea>
            <p class="mt-1 text-xs text-slate-400">Required once the status is <strong>Resolved</strong> or <strong>Dismissed</strong>.</p>
            @error('resolution_notes')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </section>

    <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-6">
        <button type="submit"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 disabled:cursor-wait disabled:opacity-70">
            {{ $blotter?->exists ? 'Save changes' : 'Record entry' }}
        </button>
        <a href="{{ route('blotter.index') }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            Cancel
        </a>
    </div>
</form>
