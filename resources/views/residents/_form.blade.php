{{-- Shared create / edit form for a resident.
     Expects: $action, $method, $resident, $puroks, $households [, $submitLabel] --}}
@php($resident = $resident ?? new \App\Models\Resident())

<form method="POST" action="{{ $action }}"
      enctype="multipart/form-data"
      data-submit-loading data-loading-label="Saving…"
      class="space-y-8">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    {{-- ── Identity ── --}}
    <section>
        <h2 class="text-sm font-semibold text-slate-900">Identity</h2>
        <p class="mt-0.5 text-xs text-slate-500">Names exactly as written on official documents.</p>

        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="first_name" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    First name <span class="text-red-500">*</span>
                </label>
                <input id="first_name" type="text" name="first_name"
                       value="{{ old('first_name', $resident->first_name) }}"
                       required maxlength="80" autocomplete="off"
                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('first_name') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @error('first_name')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="middle_name" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Middle name</label>
                <input id="middle_name" type="text" name="middle_name"
                       value="{{ old('middle_name', $resident->middle_name) }}"
                       maxlength="80" autocomplete="off"
                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('middle_name') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @error('middle_name')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="last_name" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Last name <span class="text-red-500">*</span>
                </label>
                <input id="last_name" type="text" name="last_name"
                       value="{{ old('last_name', $resident->last_name) }}"
                       required maxlength="80" autocomplete="off"
                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('last_name') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @error('last_name')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="suffix" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Suffix</label>
                <input id="suffix" type="text" name="suffix"
                       value="{{ old('suffix', $resident->suffix) }}"
                       maxlength="20" autocomplete="off" placeholder="Jr., Sr., III"
                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('suffix') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @error('suffix')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    <hr class="border-slate-100">

    {{-- ── Personal details ── --}}
    <section>
        <h2 class="text-sm font-semibold text-slate-900">Personal details</h2>
        <p class="mt-0.5 text-xs text-slate-500">Age is computed automatically from the date of birth.</p>

        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="birth_date" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Date of birth <span class="text-red-500">*</span>
                </label>
                <input id="birth_date" type="date" name="birth_date"
                       value="{{ old('birth_date', $resident->birth_date?->format('Y-m-d')) }}"
                       required max="{{ now()->format('Y-m-d') }}"
                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('birth_date') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @error('birth_date')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="sex" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Sex <span class="text-red-500">*</span>
                </label>
                <select id="sex" name="sex" required
                        class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('sex') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                    <option value="">Select…</option>
                    @foreach (\App\Models\Resident::SEXES as $option)
                        <option value="{{ $option }}" @selected(old('sex', $resident->sex) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @error('sex')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="civil_status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Civil status <span class="text-red-500">*</span>
                </label>
                <select id="civil_status" name="civil_status" required
                        class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('civil_status') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                    <option value="">Select…</option>
                    @foreach (\App\Models\Resident::CIVIL_STATUSES as $option)
                        <option value="{{ $option }}" @selected(old('civil_status', $resident->civil_status) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @error('civil_status')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="occupation" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Occupation</label>
                <input id="occupation" type="text" name="occupation"
                       value="{{ old('occupation', $resident->occupation) }}"
                       maxlength="100" autocomplete="off" placeholder="e.g. Farmer, Teacher"
                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('occupation') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @error('occupation')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    <hr class="border-slate-100">

    {{-- ── Contact ── --}}
    <section>
        <h2 class="text-sm font-semibold text-slate-900">Contact &amp; address</h2>

        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="phone" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</label>
                <input id="phone" type="tel" name="phone"
                       value="{{ old('phone', $resident->phone) }}"
                       maxlength="30" autocomplete="off" placeholder="+63 912 345 6789"
                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('phone') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @error('phone')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Email</label>
                <input id="email" type="email" name="email"
                       value="{{ old('email', $resident->email) }}"
                       maxlength="150" autocomplete="off"
                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('email') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @error('email')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="address" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Address</label>
                <textarea id="address" name="address" rows="2" maxlength="255"
                          placeholder="House number, street, barangay"
                          class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('address') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">{{ old('address', $resident->address) }}</textarea>
                <p class="mt-1 text-xs text-slate-400">Leave blank to fall back to the household address.</p>
                @error('address')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    <hr class="border-slate-100">

    {{-- ── Assignment ── --}}
    <section>
        <h2 class="text-sm font-semibold text-slate-900">Assignment</h2>
        <p class="mt-0.5 text-xs text-slate-500">Where the resident belongs inside the barangay.</p>

        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="purok_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Purok</label>
                <select id="purok_id" name="purok_id"
                        class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('purok_id') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                    <option value="">— No purok assigned —</option>
                    @foreach ($puroks as $purok)
                        <option value="{{ $purok->id }}" @selected(old('purok_id', $resident->purok_id) == $purok->id)>
                            {{ $purok->label() }}
                        </option>
                    @endforeach
                </select>
                @error('purok_id')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="household_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Household</label>
                <select id="household_id" name="household_id"
                        class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('household_id') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                    <option value="">— No household —</option>
                    @foreach ($households as $household)
                        <option value="{{ $household->id }}" @selected(old('household_id', $resident->household_id) == $household->id)>
                            {{ $household->household_number }} &mdash; {{ \Illuminate\Support\Str::limit($household->address, 36) }} ({{ $household->status }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">Only Occupied or Under Construction households accept members.</p>
                @error('household_id')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    <hr class="border-slate-100">

    {{-- ── Photo ── --}}
    <section>
        <h2 class="text-sm font-semibold text-slate-900">Photo</h2>
        <p class="mt-0.5 text-xs text-slate-500">Optional. Stored privately and shown only to office users and the resident.</p>

        <div class="mt-3">
            <input id="photo" type="file" name="photo"
                   accept="image/jpeg,image/png,image/webp"
                   class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border file:border-slate-300 file:bg-white file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-50">
            <p class="mt-1 text-xs text-slate-400">JPG, JPEG, PNG or WebP &mdash; up to 2 MB.</p>
            @error('photo')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror

            @if ($resident->exists && $resident->photo_path)
                <div class="mt-3 flex flex-wrap items-center gap-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <img src="{{ route('residents.photo', $resident) }}"
                         alt="Current photo of {{ $resident->full_name }}"
                         class="h-20 w-20 rounded-lg object-cover ring-1 ring-slate-200">

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remove_photo" value="1"
                               @checked(old('remove_photo'))
                               class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                        Remove the current photo
                    </label>
                </div>
            @endif
        </div>
    </section>

    {{-- ── Status (administrators, editing only) ── --}}
    @if ($resident->exists && auth()->user()->isAdmin())
        <hr class="border-slate-100">

        <section>
            <h2 class="text-sm font-semibold text-slate-900">Record status</h2>

            <div class="mt-3 max-w-sm">
                <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                <select id="status" name="status"
                        class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('status') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                    @foreach ([\App\Models\Resident::STATUS_ACTIVE, \App\Models\Resident::STATUS_ARCHIVED] as $option)
                        <option value="{{ $option }}" @selected(old('status', $resident->status) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">
                    Archiving also suspends the resident's portal account.
                </p>
                @error('status')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </section>
    @endif

    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
        <button type="submit"
                class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 disabled:cursor-wait disabled:opacity-70">
            {{ $submitLabel ?? 'Save resident' }}
        </button>

        <a href="{{ $resident->exists ? route('residents.show', $resident) : route('residents.index') }}"
           class="text-sm text-slate-500 transition hover:text-slate-800">
            Cancel
        </a>
    </div>
</form>
