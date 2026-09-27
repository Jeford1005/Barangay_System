{{-- Shared CRUD action: create/update (green), view (blue), delete (red). --}}
@props([
    'href' => null,
    'variant' => 'create',
    'compact' => false,
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 rounded-lg text-sm font-semibold text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60';
    $sizeClasses = $compact
        ? 'min-h-10 whitespace-nowrap px-3 py-2'
        : 'min-h-11 px-3 py-2.5';

    $variantClasses = match ($variant) {
        'view' => 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500 active:bg-blue-800',
        'update' => 'bg-green-600 hover:bg-green-700 focus:ring-green-500 active:bg-green-800',
        'delete' => 'bg-red-600 hover:bg-red-700 focus:ring-red-500 active:bg-red-800',
        default => 'bg-green-600 hover:bg-green-700 focus:ring-green-500 active:bg-green-800',
    };

    $classes = $baseClasses.' '.$sizeClasses.' '.$variantClasses;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="button" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
