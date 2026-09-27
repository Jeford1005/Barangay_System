{{-- Shared create / edit form for a purok. Expects: $action, $method, $purok [, $submitLabel] --}}
@php($purok = $purok ?? new \App\Models\Purok())

<form method="POST" action="{{ $action }}"
      data-submit-loading data-loading-label="Saving…"
      class="space-y-4">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <div>
        <label for="purok_name" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
            Name <span class="text-red-500">*</span>
        </label>
        <input id="purok_name"
               type="text"
               name="name"
               value="{{ old('name', $purok->name) }}"
               required
               maxlength="50"
               autocomplete="off"
               placeholder="e.g. Purok 1"
               class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('name') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
        @error('name')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="purok_code" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Code</label>
        <input id="purok_code"
               type="text"
               name="code"
               value="{{ old('code', $purok->code) }}"
               maxlength="10"
               autocomplete="off"
               placeholder="e.g. P1"
               class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('code') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
        <p class="mt-1 text-xs text-slate-400">
            Short code used in dropdowns. Leave blank to auto-generate when creating, or to keep the current code when editing.
        </p>
        @error('code')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="purok_description" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Description</label>
        <textarea id="purok_description"
                  name="description"
                  rows="3"
                  maxlength="255"
                  placeholder="Optional note about the zone"
                  class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('description') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">{{ old('description', $purok->description) }}</textarea>
        @error('description')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <button type="submit"
            class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 disabled:cursor-wait disabled:opacity-70">
        {{ $submitLabel ?? 'Save purok' }}
    </button>
</form>
