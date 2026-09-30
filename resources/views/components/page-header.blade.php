{{-- Pinned page title bar. Rendered by app-layout OUTSIDE the scroll region
     (flex sibling above #content-scroll), so it can never scroll away — the
     content area below it is the only thing that scrolls.
     On mobile this bar ALSO hosts the hamburger (the black top header is gone),
     so the toggle is rendered here and hidden on desktop.
     Usage:
       @section('page_header')
           Either slot form works — use whichever reads better:
           <x-page-header title="Residents" subtitle="5 active residents">
               <a href="...">Add Resident</a>
           </x-page-header>

           <x-page-header title="Residents" subtitle="5 active residents">
               <x-slot:actions><a href="...">Add Resident</a></x-slot:actions>
           </x-page-header>
       @endsection
     Print sheets simply don't provide the section. --}}
@props([
    'title' => '',
    'subtitle' => null,
])

<div class="no-print shrink-0 bg-white border-b border-slate-200 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 w-full flex min-h-14 items-center justify-between gap-3">
        <div class="flex min-w-0 items-start gap-1">
            {{-- Mobile hamburger: opens the sidebar drawer (black header removed).
                 Absent in embed mode — the settings dialog's iframe has no drawer. --}}
            @unless (request()->boolean('embed'))
            <button type="button" id="sidebar-toggle"
                class="lg:hidden btn btn-ghost btn-icon -ml-2"
                aria-expanded="false" aria-controls="sidebar" onclick="toggleSidebar()">
                <span class="sr-only">Toggle navigation</span>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            @endunless
            <div class="min-w-0">
                <h1 class="break-words text-xl font-bold text-slate-900">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-0.5 break-words text-sm text-slate-500">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        @php
            // Header buttons may arrive as a named `actions` slot or as the
            // default slot (the usage shown above). Render whichever one has
            // content so neither can be silently dropped.
            $headerActions = $actions ?? $slot;
            $hasHeaderActions = trim((string) $headerActions) !== '';
        @endphp
        @if ($hasHeaderActions)
            <div class="flex flex-wrap items-center gap-2 shrink-0">{{ $headerActions }}</div>
        @endif
    </div>
</div>
