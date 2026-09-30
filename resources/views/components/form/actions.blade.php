@props(['cancelHref', 'submitLabel'])

<div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
    <a href="{{ $cancelHref }}" class="btn btn-neutral">
        Cancel
    </a>
    <button type="submit" class="btn btn-primary">
        {{ $submitLabel }}
    </button>
</div>
