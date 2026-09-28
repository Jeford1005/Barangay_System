@props(['active' => false, 'icon' => null])

@php
    $classes = 'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors min-h-11 '.
        ($active ? 'bg-slate-800 text-white shadow-inner' : 'text-slate-300 hover:text-white hover:bg-slate-800/70');
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
    @endif
    {{ $slot }}
</a>
