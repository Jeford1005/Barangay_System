@php
    // Collect messages from every convention the app uses:
    // Laravel flash (session), Inertia-style props, and validation errors.
    $toasts = collect();

    if (session('success')) {
        $toasts->push(['type' => 'success', 'message' => session('success')]);
    }
    if (session('error')) {
        $toasts->push(['type' => 'error', 'message' => session('error')]);
    }
    if (session('status')) {
        $toasts->push(['type' => 'success', 'message' => session('status')]);
    }
    if (session('warning')) {
        $toasts->push(['type' => 'warning', 'message' => session('warning')]);
    }
    // $errors is shared by the web middleware group; router-level error
    // pages (404/419/500) render without it, so stay null-safe here.
    $sharedErrors = $errors ?? null;
    if ($sharedErrors?->any()) {
        // Validation failures read best as a single toast (the first message)
        // plus an inline error block where the form already shows details.
        $toasts->push(['type' => 'error', 'message' => $sharedErrors->first()]);
    }
@endphp

<div id="toast-region"
    aria-live="polite"
    aria-atomic="false"
    class="fixed top-4 right-4 z-[100] flex flex-col items-end gap-2 pointer-events-none w-[calc(100%-2rem)] sm:w-96">

    @foreach ($toasts as $toast)
        <div class="toast pointer-events-auto w-full flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg backdrop-blur bg-white/95
            {{ ['success' => 'border-emerald-200', 'error' => 'border-red-200', 'warning' => 'border-amber-200'][$toast['type']] ?? 'border-slate-200' }}"
            role="status">
            <span class="mt-0.5 shrink-0">
                @if ($toast['type'] === 'success')
                    <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg>
                @elseif ($toast['type'] === 'error')
                    <svg class="h-5 w-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                @else
                    <svg class="h-5 w-5 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                @endif
            </span>
            <p class="flex-1 text-sm font-medium text-slate-800">{{ $toast['message'] }}</p>
            <button type="button" class="shrink-0 rounded-md p-1 text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none" aria-label="Dismiss" onclick="this.closest('.toast').remove()">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
    @endforeach
</div>

<style>
    /* Entrance/exit animation: slide in from the right, fade out before removal. */
    .toast {
        animation: toast-in .25s cubic-bezier(.21, 1.02, .73, 1) both;
    }
    .toast.toast-leaving {
        animation: toast-out .18s ease-in both;
    }
    @keyframes toast-in {
        from { opacity: 0; transform: translateX(24px); }
        to   { opacity: 1; transform: translateX(0); }
    }
    @keyframes toast-out {
        from { opacity: 1; transform: translateX(0); }
        to   { opacity: 0; transform: translateX(24px); }
    }
    @media (prefers-reduced-motion: reduce) {
        .toast, .toast.toast-leaving { animation: none; }
    }
</style>

<script>
    (function () {
        var region = document.getElementById('toast-region');
        if (!region) return;

        var DURATION = 4500;

        region.querySelectorAll('.toast').forEach(function (toast) {
            // Auto-dismiss with a gentle exit animation.
            var timer = setTimeout(function () { dismiss(toast); }, DURATION);

            // Pausing on hover so slow readers are not punished.
            toast.addEventListener('mouseenter', function () { clearTimeout(timer); });
            toast.addEventListener('mouseleave', function () {
                timer = setTimeout(function () { dismiss(toast); }, 2000);
            });

            toast.querySelector('button[aria-label="Dismiss"]')
                ?.addEventListener('click', function () { clearTimeout(timer); dismiss(toast); });
        });

        function dismiss(toast) {
            if (!toast.isConnected) return;
            toast.classList.add('toast-leaving');
            toast.addEventListener('animationend', function () { toast.remove(); }, { once: true });
            // Fallback in case animationend never fires (reduced motion).
            setTimeout(function () { toast.remove(); }, 400);
        }

        // Programmatic API for fetch flows (e.g. the login-page dialogs):
        // window.showToast('Saved', 'success')
        window.showToast = function (message, type) {
            var colors = {
                success: 'border-emerald-200',
                error: 'border-red-200',
                warning: 'border-amber-200',
            };
            var el = document.createElement('div');
            el.className = 'toast pointer-events-auto w-full flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg backdrop-blur bg-white/95 ' + (colors[type] || 'border-slate-200');
            el.setAttribute('role', 'status');
            el.innerHTML =
                '<p class="flex-1 text-sm font-medium text-slate-800"></p>' +
                '<button type="button" class="shrink-0 rounded-md p-1 text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none" aria-label="Dismiss">' +
                '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
                '</button>';
            el.querySelector('p').textContent = message;
            el.querySelector('button').addEventListener('click', function () { dismiss(el); });
            region.appendChild(el);
            setTimeout(function () { dismiss(el); }, DURATION);
        };

        // Fetch-based CRUD requests follow the redirect before the page is
        // reloaded, which consumes Laravel's one-shot flash data. Carry the
        // success message across that reload so it still appears on the right.
        try {
            var pending = JSON.parse(sessionStorage.getItem('crud-toast') || 'null');
            if (pending && pending.message) {
                sessionStorage.removeItem('crud-toast');
                window.showToast(pending.message, pending.type || 'success');
            }
        } catch (error) {
            // Ignore malformed or unavailable session storage.
        }
    })();
</script>
