{{-- ============================================================
   Shared CRUD dialog. Same dialog pattern as the login-page
   forgot-password / create-account dialogs:
     • fixed backdrop, NO blur — just a dim overlay
     • panel scrolls independently; page behind never scrolls
     • open triggers are declarative: [data-dialog-open="dialog-id"]
   The form content is injected from the existing create/edit
   pages (fetched with ?fragment=1), so validation, CSRF, and
   the old() flow keep working unchanged.
   Usage:
     <x-crud-dialog id="resident-dialog" title="Register Resident"
                    description="Resident records and barangay clearance data"
                    fetch-base="{{ route('residents.create') }}"
                    size="xl" />
     <a href="{{ route('residents.create') }}" data-dialog-open="resident-dialog">Add</a>
     <a href="{{ route('residents.edit', $resident->id) }}"
        data-dialog-open="resident-dialog"
        data-fetch-url="{{ route('residents.edit', $resident->id) }}"
        data-fetch-mode="edit">Edit</a>
   ============================================================ --}}
@props([
    'id',
    'title',
    'description' => null,
    'fetchBase' => null,   // create URL: GET ?fragment=1 returns the bare form card
    'size' => 'md',        // md | lg | xl | full
    'openOnSuccess' => false, // certificates: open the redirect target (print sheet) in a new tab
])

@php
$panelWidth = [
    'md' => 'max-w-lg',
    'lg' => 'max-w-3xl',
    'xl' => 'max-w-5xl',
    'full' => 'max-w-none',
][$size] ?? 'max-w-lg';
@endphp

<div id="{{ $id }}" class="crud-dialog fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" @if($fetchBase) data-fetch-base="{{ $fetchBase }}" @endif @if($openOnSuccess) data-open-on-success="true" @endif>
    {{-- Backdrop: plain dim, NO backdrop-blur (per design request). --}}
    <div class="crud-dialog-backdrop absolute inset-0 bg-slate-950/60"></div>

    {{-- Center the panel in the viewport; only the form body scrolls. --}}
    <div class="crud-dialog-viewport absolute inset-0 overflow-hidden">
        <div class="flex min-h-full items-center justify-center p-2 sm:p-4">
            <div tabindex="-1" class="crud-dialog-panel modal-panel relative flex max-h-[calc(100vh-1rem)] w-full flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl focus:outline-none sm:max-h-[calc(100vh-2rem)] {{ $panelWidth }}">

                {{-- Header --}}
                <div class="z-10 flex shrink-0 items-start justify-between gap-4 rounded-t-2xl border-b border-slate-200 bg-white px-6 py-5">
                    <div class="min-w-0">
                        <h2 id="{{ $id }}-title" class="text-base font-semibold tracking-tight text-slate-900">{{ $title }}</h2>
                        @if ($description)
                            <p class="mt-0.5 text-xs text-slate-500">{{ $description }}</p>
                        @endif
                    </div>
                    <button
                        type="button"
                        data-dialog-close
                        aria-label="Close dialog"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Body: long forms scroll here, while the dialog stays centered. --}}
                <div class="crud-dialog-body min-h-0 flex-1 overflow-y-auto px-6 py-6">
                    <div class="crud-dialog-skeleton space-y-3" data-skeleton>
                        <div class="h-5 w-40 animate-pulse rounded bg-slate-200"></div>
                        <div class="h-10 w-full animate-pulse rounded bg-slate-100"></div>
                        <div class="h-10 w-full animate-pulse rounded bg-slate-100"></div>
                        <div class="h-10 w-full animate-pulse rounded bg-slate-100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
