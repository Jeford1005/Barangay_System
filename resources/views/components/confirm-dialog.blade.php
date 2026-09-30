{{-- ============================================================
   Shared confirmation dialog — the styled replacement for the
   native browser confirm() box ("barangay-system-ochre.vercel.app
   says / Delete household HH-001?").

   Trigger it from any form:

     <form method="POST" action="..."
           data-confirm="Delete household HH-001? This cannot be undone."
           data-confirm-title="Delete household"
           data-confirm-accept="Delete"
           data-confirm-icon="trash"
           data-confirm-tone="danger">
       ...
     </form>

   Copy rule — the header names the object, the message asks the
   question, and the buttons carry bare verbs only:

     header  data-confirm-title   "Remove photo"
     message data-confirm         "Remove your profile photo?"
     buttons accept "Remove" · dismiss "Cancel"
                                  (never "Remove photo" / "Keep photo")

   When the verb itself is "Cancel" (cancel-request flows), the
   dismiss button becomes "Keep" so the pair never repeats itself.

   • tone defaults to "danger" (red) for destructive actions; pass
     data-confirm-tone="primary" (sky) for reversible ones such as
     reactivate, send reset code, clear cache.
   • data-confirm-icon picks the icon: trash, x-circle, x-mark,
     archive-box, check-circle, envelope, cog-6-tooth — the warning
     triangle is the default. SVG paths mirror the registry in
     components/icon.blade.php but are inlined here so the dialog
     controls its own size (h-7) instead of fighting the x-icon
     default.
   • Without JS the form submits untouched — progressive enhancement.
   • Markup mirrors <x-error-dialog> (dim backdrop, no blur, rounded-xl
     panel, centered icon → header → message → actions) so both cards
     look native to the app.
   ============================================================ --}}
<div
    id="confirm-dialog"
    class="confirm-dialog fixed inset-0 z-[60] hidden"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="confirm-dialog-title"
    aria-describedby="confirm-dialog-message"
>
    <div class="confirm-dialog-backdrop modal-backdrop absolute inset-0 bg-slate-950/60"></div>

    <div class="absolute inset-0 overflow-hidden">
        <div class="flex min-h-full items-center justify-center p-4">
            <div tabindex="-1" class="confirm-panel modal-panel relative w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl focus:outline-none">

                <div class="flex flex-col items-center px-6 pt-6 text-center">
                    <span
                        data-confirm-dialog-icon
                        data-classes-danger="dialog-icon-pop text-red-600"
                        data-classes-primary="dialog-icon-pop text-sky-600"
                        class="dialog-icon-pop text-red-600"
                    >
                        <svg data-confirm-icon-for="warning" class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                        <svg data-confirm-icon-for="trash" class="h-7 w-7 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        <svg data-confirm-icon-for="x-circle" class="h-7 w-7 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        <svg data-confirm-icon-for="x-mark" class="h-7 w-7 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M6 6l12 12"/></svg>
                        <svg data-confirm-icon-for="archive-box" class="h-7 w-7 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                        <svg data-confirm-icon-for="check-circle" class="h-7 w-7 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        <svg data-confirm-icon-for="envelope" class="h-7 w-7 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                        <svg data-confirm-icon-for="cog-6-tooth" class="h-7 w-7 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.52 6.52 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    </span>

                    <h2 id="confirm-dialog-title" data-confirm-dialog-title
                        class="dialog-stagger mt-3 text-base font-semibold tracking-tight text-slate-900"
                        style="animation-delay: 40ms">
                        Are you sure?
                    </h2>

                    <p id="confirm-dialog-message" data-confirm-dialog-message
                        class="dialog-stagger mt-2 text-sm leading-6 text-slate-600"
                        style="animation-delay: 80ms"></p>
                </div>

                <div class="dialog-stagger flex flex-col-reverse gap-2 px-6 pb-6 pt-5 sm:flex-row sm:justify-center"
                    style="animation-delay: 120ms">
                    <button
                        type="button"
                        data-confirm-dialog-cancel
                        class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2 transition active:scale-[.98] shadow-sm"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        data-confirm-dialog-accept
                        data-classes-danger="min-h-11 rounded-lg px-5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2 transition active:scale-[.98] shadow-md shadow-red-600/30 border border-red-700"
                        data-classes-primary="min-h-11 rounded-lg px-5 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2 transition active:scale-[.98] shadow-md shadow-sky-600/30 border border-sky-700"
                        class="min-h-11 rounded-lg px-5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2 transition active:scale-[.98] shadow-md shadow-red-600/30 border border-red-700"
                    >
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
