@php($isEdit = $welfare !== null)

<form method="POST"
      action="{{ $isEdit ? route('welfare.update', $welfare) : route('welfare.store') }}"
      data-submit-loading
      data-loading-label="{{ $isEdit ? 'Saving…' : 'Recording…' }}"
      class="space-y-5">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- ── resident ── --}}
    <div>
        <label for="resident_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Resident</label>
        <select id="resident_id"
                name="resident_id"
                required
                class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('resident_id') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
            <option value="">Select a resident</option>
            @foreach ($residents as $resident)
                <option value="{{ $resident->id }}" @selected((string) old('resident_id', $welfare?->resident_id) === (string) $resident->id)>
                    {{ $resident->full_name }}{{ $resident->purok ? ' — '.$resident->purok->name : '' }}
                </option>
            @endforeach
        </select>
        @error('resident_id')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-5 sm:grid-cols-3">
        {{-- ── assistance type ── --}}
        <div>
            <label for="assistance_type" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Assistance type</label>
            <select id="assistance_type"
                    name="assistance_type"
                    required
                    class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('assistance_type') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @if (! $isEdit)
                    <option value="">Select a type</option>
                @endif
                @foreach ($assistanceTypes as $type)
                    <option value="{{ $type }}" @selected((string) old('assistance_type', $welfare?->assistance_type) === $type)>{{ $type }}</option>
                @endforeach
            </select>
            @error('assistance_type')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- ── requested amount ── --}}
        <div>
            <label for="requested_amount" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Requested amount (&#8369;)</label>
            <input id="requested_amount"
                   type="number"
                   name="requested_amount"
                   value="{{ old('requested_amount', $welfare?->requested_amount) }}"
                   required
                   min="0"
                   max="99999999.99"
                   step="0.01"
                   placeholder="0.00"
                   inputmode="decimal"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('requested_amount') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
            @error('requested_amount')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- ── request date ── --}}
        <div>
            <label for="request_date" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Request date</label>
            <input id="request_date"
                   type="date"
                   name="request_date"
                   value="{{ old('request_date', $welfare?->request_date?->format('Y-m-d') ?? now()->toDateString()) }}"
                   required
                   max="{{ now()->toDateString() }}"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('request_date') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
            @error('request_date')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @if ($isEdit)
        <div class="grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2">
            {{-- ── status (review workflow) ── --}}
            <div>
                <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                <select id="status"
                        name="status"
                        required
                        class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('status') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                    @foreach ($statuses as $option)
                        <option value="{{ $option }}" @selected((string) old('status', $welfare->status) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @error('status')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- ── granted amount ── --}}
            <div>
                <label for="amount" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Granted amount (&#8369;)</label>
                <input id="amount"
                       type="number"
                       name="amount"
                       value="{{ old('amount', $welfare?->amount) }}"
                       min="0"
                       max="99999999.99"
                       step="0.01"
                       placeholder="Leave empty if none granted"
                       inputmode="decimal"
                       class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('amount') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                @error('amount')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2">
                <p class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-500">
                    <span class="font-semibold text-slate-700">Workflow:</span>
                    approving or releasing needs a granted amount of at least &#8369;1.00 (never above the
                    requested amount); denial needs a reason in the notes; nothing may be granted while the
                    request is still <em>Requested</em> or <em>Under Review</em>.
                </p>
            </div>
        </div>
    @endif

    {{-- ── notes ── --}}
    <div>
        <label for="notes" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</label>
        <textarea id="notes"
                  name="notes"
                  rows="3"
                  maxlength="2000"
                  placeholder="Purpose, circumstances, reason for denial…"
                  class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('notes') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">{{ old('notes', $welfare?->notes) }}</textarea>
        @error('notes')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
        <button type="submit"
                class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 disabled:cursor-wait disabled:opacity-70">
            {{ $isEdit ? 'Save changes' : 'Record request' }}
        </button>

        <a href="{{ route('welfare.index') }}"
           class="text-sm font-medium text-slate-500 transition hover:text-slate-800">
            Cancel
        </a>
    </div>
</form>
