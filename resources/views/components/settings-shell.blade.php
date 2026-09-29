@props([
    'current' => null,
])

@if (request()->boolean('embed'))
    {{-- Embed mode: this page is rendered inside the settings dialog's iframe,
         which supplies the header and the section navigation itself. Only the
         section content travels; scripts, toasts and dialogs stay as they are. --}}
    {{ $slot }}
@else
<div class="mx-auto max-w-6xl">
    <div class="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:items-start">
        <aside class="min-w-0 border-b border-slate-200 pb-4 lg:border-b-0 lg:border-r lg:pb-0 lg:pr-5">
            <p class="px-2 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Settings</p>
            <x-settings-nav :current="$current" />
        </aside>

        <div class="min-w-0">
            {{ $slot }}
        </div>
    </div>
</div>
@endif
