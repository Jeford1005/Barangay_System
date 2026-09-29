{{--
    Settings dialog: the settings module as an in-page pop-up, opened from
    the sidebar's Settings link via resources/js/settings-dialog.js.

    Design: the dialog wears the product's own chrome instead of a generic
    white modal — a slate-900 rail (the app sidebar's #0F172A) carries the
    title, close control and section list in the sidebar's nav-row language;
    the light pane frames the section itself, headed by the active section
    name that JS keeps in sync with the frame.

    Progressive enhancement only — the link keeps its real href, so without
    JS (or for anyone who deep-links) the settings URLs still render as full
    pages. The pane is a same-origin iframe pointed at the section URL with
    ?embed=1; app-layout strips the app chrome for that flag while keeping
    scripts, toasts and the confirm/error dialogs, so forms, filters and
    downloads inside the pane behave exactly as they do on the page.
--}}
<div id="settings-dialog"
     class="settings-dialog fixed inset-0 z-[100] hidden"
     role="dialog" aria-modal="true" aria-labelledby="settings-dialog-title"
     data-settings-dialog>
    <div class="modal-backdrop absolute inset-0 bg-slate-950/60" data-settings-dialog-backdrop></div>

    <div class="pointer-events-none relative flex h-full w-full items-stretch justify-center sm:items-center sm:p-4">
        <div data-settings-dialog-panel
             class="modal-panel pointer-events-auto flex h-full w-full flex-col overflow-hidden bg-white sm:h-[min(88vh,880px)] sm:max-w-5xl sm:rounded-xl sm:border sm:border-slate-200 sm:shadow-2xl md:flex-row">

            {{-- Dark rail: app sidebar language, so the dialog reads as part
                 of the product rather than a bolted-on browser prompt. --}}
            <div class="flex shrink-0 flex-col bg-slate-900 text-white md:w-64">
                <div class="flex h-14 shrink-0 items-center justify-between border-b border-white/10 pl-4 pr-1">
                    <h2 id="settings-dialog-title" class="text-xs font-bold uppercase tracking-[0.18em] text-white/70">Settings</h2>
                    <button type="button" data-settings-dialog-close
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-300 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-sky-600"
                        aria-label="Close settings" title="Close settings">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <x-settings-nav variant="rail" />
            </div>

            <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                <div class="hidden h-14 shrink-0 items-center border-b border-slate-200 bg-white px-5 md:flex">
                    <p data-settings-dialog-section class="text-sm font-semibold text-slate-900">Settings</p>
                </div>
                <iframe data-settings-frame src="about:blank" title="Settings"
                    class="min-h-0 w-full flex-1 border-0 bg-slate-50"></iframe>
            </div>
        </div>
    </div>
</div>
