@props(['label' => 'Print'])

<button type="button" {{ $attributes->merge(['class' => 'no-print inline-flex min-h-11 items-center gap-2 rounded-lg border border-sky-200 bg-white px-3 py-2 text-sm font-medium text-sky-700 hover:bg-sky-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600']) }}
    onclick="window.print()"
    aria-label="{{ $label }} this page">
    <x-icon name="printer" class="h-4 w-4" />
    {{ $label }}
</button>
