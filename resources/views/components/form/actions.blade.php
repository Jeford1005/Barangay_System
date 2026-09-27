@props(['cancelHref', 'submitLabel', 'color' => 'green'])

@php
// CRUD color semantics: create/update = green, delete = red.
$palette = match ($color) {
    'amber' => ['bg-green-600 hover:bg-green-700', 'focus:ring-green-500'],
    default => ['bg-green-600 hover:bg-green-700', 'focus:ring-green-500'],
};
@endphp

<div class="flex items-center justify-end gap-3 border-t border-neutral-200 pt-4">
    <a href="{{ $cancelHref }}" class="inline-flex min-h-10 items-center rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 focus:outline-none focus:ring-2 focus:ring-neutral-400">
        Cancel
    </a>
    <button type="submit" class="min-h-10 rounded-lg px-3 py-2 text-sm font-semibold text-white {{ $palette[0] }} focus:outline-none focus:ring-2 {{ $palette[1] }} focus:ring-offset-2 transition-colors">
        {{ $submitLabel }}
    </button>
</div>
