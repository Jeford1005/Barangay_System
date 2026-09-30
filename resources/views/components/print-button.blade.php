@props(['label' => 'Print'])

<button type="button" {{ $attributes->merge(['class' => 'no-print btn btn-outline']) }}
    onclick="window.print()"
    aria-label="{{ $label }} this page">
    <x-icon name="printer" class="h-4 w-4" />
    {{ $label }}
</button>
