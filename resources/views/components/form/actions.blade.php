@props(['cancelHref', 'submitLabel'])

<div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
    <a href="{{ $cancelHref }}" class="inline-flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-400">
        Cancel
    </a>
    <button type="submit" class="min-h-11 rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2">
        {{ $submitLabel }}
    </button>
</div>
