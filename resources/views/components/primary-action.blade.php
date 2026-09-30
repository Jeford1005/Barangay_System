{{-- Shared CRUD action. One .btn family: sky carries every primary action,
     red is reserved for delete. The variants stay so call sites can name
     their intent; `compact` switches to the toolbar-sized row width. --}}
@props([
    'href' => null,
    'variant' => 'create',
    'compact' => false,
])

@php
    $classes = trim('btn'.($compact ? ' btn-row' : '').' '.($variant === 'delete' ? 'btn-danger' : 'btn-primary'));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="button" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
