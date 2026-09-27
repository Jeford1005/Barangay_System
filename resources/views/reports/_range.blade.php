{{--
    Shared ?from=&to= range picker for the report pages.

    Expects: $from, $to (Y-m-d or null), $action (form target URL),
             optional $hint (one-line help text).
--}}
<form method="GET" action="{{ $action }}" class="mb-6 flex flex-wrap items-end gap-3 print:hidden">
    <div>
        <label for="range-from" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">From</label>
        <input id="range-from"
               type="date"
               name="from"
               value="{{ $from }}"
               max="{{ now()->toDateString() }}"
               class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('from') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
        @error('from')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="range-to" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">To</label>
        <input id="range-to"
               type="date"
               name="to"
               value="{{ $to }}"
               max="{{ now()->toDateString() }}"
               class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('to') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
        @error('to')
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex gap-2">
        <button type="submit"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
            Apply range
        </button>
        <a href="{{ $action }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            Reset
        </a>
    </div>

    @if (! empty($hint))
        <p class="w-full text-xs text-slate-400 sm:w-auto">{{ $hint }}</p>
    @endif
</form>
