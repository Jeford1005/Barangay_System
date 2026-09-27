@props(['label' => 'Print'])

<button type="button" {{ $attributes->merge(['class' => 'no-print inline-flex min-h-10 items-center gap-2 rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500']) }}
    onclick="window.print()"
    aria-label="{{ $label }} this page">
    <x-icon name="printer" class="h-4 w-4" />
    {{ $label }}
</button>
