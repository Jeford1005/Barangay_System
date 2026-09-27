@php($isEdit = $household !== null)

<form method="POST"
      action="{{ $isEdit ? route('households.update', $household) : route('households.store') }}"
      data-submit-loading
      data-loading-label="{{ $isEdit ? 'Saving…' : 'Creating…' }}"
      class="space-y-5">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- ── household number: generated on create, locked afterwards ── --}}
    <div>
        <label for="household_number" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Household number</label>
        <input id="household_number"
               type="text"
               value="{{ old('household_number', $household?->household_number ?? $nextNumber) }}"
               readonly
               maxlength="20"
               autocomplete="off"
               class="mt-1.5 block w-full rounded-lg border-slate-300 bg-slate-50 text-sm text-slate-600 shadow-sm focus:border-sky-500 focus:ring-sky-500">
        <input type="hidden" name="household_number" value="{{ old('household_number', $household?->household_number ?? $nextNumber) }}">
        <p class="mt-1.5 text-xs text-slate-400">Assigned automatically in HH-000 format.</p>
        @error('household_number')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- ── address ── --}}
    <div>
        <label for="address" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Address</label>
        <textarea id="address"
                  name="address"
                  rows="2"
                  required
                  maxlength="255"
                  placeholder="House number / street, Barangay Bidduang"
                  class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('address') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">{{ old('address', $household?->address) }}</textarea>
        @error('address')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- ── purok ── --}}
    <div>
        <label for="purok_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Purok</label>
        <select id="purok_id"
                name="purok_id"
                class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('purok_id') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
            <option value="">No purok assigned</option>
            @foreach ($puroks as $purok)
                <option value="{{ $purok->id }}" @selected((string) old('purok_id', $household?->purok_id) === (string) $purok->id)>{{ $purok->label() }}</option>
            @endforeach
        </select>
        @error('purok_id')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- ── head of family ── --}}
    <div>
        <label for="head_resident_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Head of family</label>
        <select id="head_resident_id"
                name="head_resident_id"
                class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('head_resident_id') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
            <option value="">No head assigned yet</option>
            @foreach ($heads as $head)
                <option value="{{ $head->id }}" @selected((string) old('head_resident_id', $household?->head_resident_id) === (string) $head->id)>
                    {{ $head->full_name }}{{ $head->purok ? ' — '.$head->purok->name : '' }}
                </option>
            @endforeach
        </select>
        <p class="mt-1.5 text-xs text-slate-400">Only active residents without another household are listed.</p>
        @error('head_resident_id')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-5 sm:grid-cols-3">
        {{-- ── house type ── --}}
        <div>
            <label for="house_type" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">House type</label>
            <select id="house_type"
                    name="house_type"
                    required
                    class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('house_type') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @foreach (\App\Models\Household::HOUSE_TYPES as $type)
                    <option value="{{ $type }}" @selected((string) old('house_type', $household?->house_type ?? 'Single') === $type)>{{ $type }}</option>
                @endforeach
            </select>
            @error('house_type')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- ── ownership ── --}}
        <div>
            <label for="ownership" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Ownership</label>
            <select id="ownership"
                    name="ownership"
                    required
                    class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('ownership') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @foreach (\App\Models\Household::OWNERSHIPS as $option)
                    <option value="{{ $option }}" @selected((string) old('ownership', $household?->ownership ?? 'Owned') === $option)>{{ $option }}</option>
                @endforeach
            </select>
            @error('ownership')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- ── status ── --}}
        <div>
            <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
            <select id="status"
                    name="status"
                    required
                    class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('status') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @foreach (\App\Models\Household::STATUSES as $option)
                    <option value="{{ $option }}" @selected((string) old('status', $household?->status ?? 'Occupied') === $option)>{{ $option }}</option>
                @endforeach
            </select>
            @error('status')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @if ($isEdit)
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Member count</p>
            <p class="mt-1 text-sm text-slate-700">
                {{ $household->member_count }} member(s) &mdash; updated automatically from the resident records.
            </p>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
        <button type="submit"
                class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 disabled:cursor-wait disabled:opacity-70">
            {{ $isEdit ? 'Save changes' : 'Create household' }}
        </button>

        <a href="{{ $isEdit ? route('households.show', $household) : route('households.index') }}"
           class="text-sm font-medium text-slate-500 transition hover:text-slate-800">
            Cancel
        </a>
    </div>
</form>
