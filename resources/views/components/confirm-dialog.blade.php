{{-- ============================================================
   Shared confirmation dialog — the styled replacement for the
   native browser confirm() box ("barangay-system-ochre.vercel.app
   says / Delete household HH-001?").

   Trigger it from any form:

     <form method="POST" action="..."
           data-confirm="Delete household HH-001?"
           data-confirm-title="Delete household"
           data-confirm-accept="Delete"
           data-confirm-tone="danger">
       ...
     </form>

   • tone defaults to "danger" (red) for destructive actions; pass
     data-confirm-tone="primary" (sky) for reversible ones such as
     reactivate, send reset code, clear cache.
   • Without JS the form submits untouched — progressive enhancement.
   • Markup mirrors <x-crud-dialog> (dim backdrop, no blur, rounded-xl
     panel, .modal-panel entrance) so it looks native to the app.
   ============================================================ --}}
<div
    id="confirm-dialog"
    class="confirm-dialog fixed inset-0 z-[60] hidden"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="confirm-dialog-title"
    aria-describedby="confirm-dialog-message"
>
    <div class="confirm-dialog-backdrop absolute inset-0 bg-slate-950/60"></div>

    <div class="absolute inset-0 overflow-hidden">
        <div class="flex min-h-full items-center justify-center p-4">
            <div tabindex="-1" class="confirm-panel modal-panel relative w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl focus:outline-none">

                <div class="flex items-start gap-3 px-6 pt-6">
                    <span
                        data-confirm-icon
                        data-classes-danger="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600"
                        data-classes-primary="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-600"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                    </span>
                    <h2 id="confirm-dialog-title" data-confirm-title class="min-w-0 pt-2 text-base font-semibold tracking-tight text-slate-900">
                        Are you sure?
                    </h2>
                </div>

                <p id="confirm-dialog-message" data-confirm-message class="px-6 pb-2 pt-3 text-sm leading-6 text-slate-600"></p>

                <div class="flex flex-col-reverse gap-2 px-6 pb-6 pt-4 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        data-confirm-cancel
                        class="min-h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        data-confirm-accept
                        data-classes-danger="min-h-10 rounded-lg px-4 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2 bg-red-600 hover:bg-red-700"
                        data-classes-primary="min-h-10 rounded-lg px-4 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2 bg-sky-600 hover:bg-sky-700"
                        class="min-h-10 rounded-lg px-4 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2 bg-red-600 hover:bg-red-700"
                    >
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
