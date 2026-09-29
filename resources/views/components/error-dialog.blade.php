{{-- ============================================================
   Shared error dialog — the red "message box" shown when something
   fails client-side before the server can answer:

     upload   a chosen file is over the limit (checked before POST),
              or the upload request itself failed (413 / non-422);
     network  fetch() itself threw — offline, DNS, connection reset.

   It never replaces a server round-trip: blocked deletes, 422
   validation and flash messages keep using the existing toasts.

   Opened from JS with the same shape as confirm():

     window.showErrorDialog({
       type:     'size' | 'upload' | 'network',   // icon
       title:    'Oops! File Too Large',
       message:  'This file is 3.1 MB. The limit is 2 MB.',
       actionLabel: 'Choose another file',
       cancel:   false,      // single-action card (defaults to true)
       retry:    function () { ... },  // runs after the card closes
       dismiss:  function () { ... },  // runs on Cancel/Esc/backdrop
     });

   Copy follows the same rule as <x-confirm-dialog>: the header names
   the problem, the message explains it, the button carries a bare
   verb. The action is tinted red (bg-red-50) because a failed
   operation is not a destructive commit — confirm dialogs keep the
   filled red/sky accepts.

   Markup mirrors <x-confirm-dialog> so both cards look like one
   family: dim backdrop, no blur, rounded-xl panel, icon → header →
   message → actions, natural-width buttons centered on sm+.
   ============================================================ --}}
<div
    id="error-dialog"
    class="error-dialog fixed inset-0 z-[60] hidden"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="error-dialog-title"
    aria-describedby="error-dialog-message"
>
    <div class="error-dialog-backdrop modal-backdrop absolute inset-0 bg-slate-950/60"></div>

    <div class="absolute inset-0 overflow-hidden">
        <div class="flex min-h-full items-center justify-center p-4">
            <div tabindex="-1" class="error-dialog-panel modal-panel relative w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl focus:outline-none">

                <div class="flex flex-col items-center px-6 pt-6 text-center">
                    <span data-error-dialog-icon class="dialog-icon-pop text-red-600">
                        <svg data-error-dialog-icon-for="upload" class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        <svg data-error-dialog-icon-for="warning" class="h-7 w-7 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                        <svg data-error-dialog-icon-for="size" class="h-7 w-7 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                        {{-- Path mirrors lucide's wifi-off; inlined like the rest of the
                             card's icons so the dialog owns its stroke width and size. --}}
                        <svg data-error-dialog-icon-for="network" class="h-7 w-7 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h.01"/><path d="M8.5 16.429a5 5 0 0 1 7 0"/><path d="M5 12.859a10 10 0 0 1 5.17-2.69"/><path d="M19 12.859a10 10 0 0 0-2.007-1.523"/><path d="M2 8.82a15 15 0 0 1 4.177-2.643"/><path d="M22 8.82a15 15 0 0 0-11.288-3.764"/><path d="m2 2 20 20"/></svg>
                    </span>

                    <h2 id="error-dialog-title" data-error-dialog-title
                        class="dialog-stagger mt-3 text-base font-semibold tracking-tight text-slate-900"
                        style="animation-delay: 40ms">
                        Something went wrong
                    </h2>

                    <p id="error-dialog-message" data-error-dialog-message
                        class="dialog-stagger mt-2 text-sm leading-6 text-slate-600"
                        style="animation-delay: 80ms"></p>
                </div>

                <div class="dialog-stagger flex flex-col-reverse gap-2 px-6 pb-6 pt-5 sm:flex-row sm:justify-center"
                    style="animation-delay: 120ms">
                    <button
                        type="button"
                        data-error-dialog-cancel
                        class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2 transition active:scale-[.98]"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        data-error-dialog-action
                        class="min-h-11 rounded-lg bg-red-50 px-4 text-sm font-semibold text-red-700 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-sky-600 focus:ring-offset-2 transition active:scale-[.98]"
                    >
                        Retry
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
