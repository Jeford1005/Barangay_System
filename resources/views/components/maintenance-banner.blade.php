{{-- Scheduled-downtime notice / active-maintenance banner.

    States (resolved here so callers never branch):
    - off     — app is up and no MAINTENANCE_NOTICE is set: renders nothing.
    - notice  — MAINTENANCE_NOTICE is set while the app is up. Amber, with an
                optional "Scheduled <from> – <to>" line from MAINTENANCE_FROM
                / MAINTENANCE_TO. Dismissible per browser session via
                sessionStorage (vanilla JS below, no framework).
    - active  — app()->isDownForMaintenance(). Red, never dismissible: it is
                only ever seen by secret-bypass sessions (`php artisan down
                --secret=...`), so hiding it would leave the one person who
                can still work unaware the public site is down.

    Presentational only: no middleware, no redirects, no cookies — the
    `php artisan down` secret-bypass flow passes through untouched. --}}
@php
    $isDown = app()->isDownForMaintenance();
    $notice = config('app.maintenance_notice.message');
    $windowFrom = config('app.maintenance_notice.from');
    $windowTo = config('app.maintenance_notice.to');
    $hasWindow = is_string($windowFrom) && $windowFrom !== '' || is_string($windowTo) && $windowTo !== '';
    $showNotice = ! $isDown && is_string($notice) && trim($notice) !== '';
@endphp

@if ($isDown)
    <div id="maintenance-active" role="alert" class="border-b border-red-200 bg-red-50 px-4 py-3 sm:px-6">
        <div class="mx-auto flex max-w-7xl items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-red-800">System under maintenance</p>
                <p class="mt-0.5 text-sm text-red-700">{{ $notice && trim((string) $notice) !== '' ? $notice : 'The system is currently under maintenance. Public access is paused until the work is done.' }}</p>
            </div>
        </div>
    </div>
@elseif ($showNotice)
    <div id="maintenance-notice" role="status" class="border-b border-amber-200 bg-amber-50 px-4 py-3 sm:px-6">
        <div class="mx-auto flex max-w-7xl items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-amber-900">
                    Scheduled maintenance
                    @if ($hasWindow)
                        <span class="font-normal text-amber-800">
                            — {{ is_string($windowFrom) && $windowFrom !== '' ? e($windowFrom) : 'soon' }} to {{ is_string($windowTo) && $windowTo !== '' ? e($windowTo) : 'until lifted' }}
                        </span>
                    @endif
                </p>
                <p class="mt-0.5 text-sm text-amber-800">{{ $notice }}</p>
            </div>
            <button type="button" data-dismiss-maintenance class="shrink-0 rounded-lg p-1 text-amber-700 hover:bg-amber-100 hover:text-amber-900 focus:outline-none focus:ring-2 focus:ring-amber-600" aria-label="Dismiss maintenance notice">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
    </div>
    <script>
        // Per-session dismissal for the *notice* state only. The active-state
        // banner above has no dismiss control and no script, so it can never
        // be hidden. sessionStorage keeps this to the current tab session —
        // no server session, no cookies, nothing the down-middleware reads.
        (function () {
            var banner = document.getElementById('maintenance-notice');
            if (!banner) return;
            try {
                if (sessionStorage.getItem('maintenance-notice-dismissed') === '1') {
                    banner.remove();
                    return;
                }
            } catch (e) { /* storage unavailable: leave the banner up */ }
            var button = banner.querySelector('[data-dismiss-maintenance]');
            if (button) {
                button.addEventListener('click', function () {
                    try { sessionStorage.setItem('maintenance-notice-dismissed', '1'); } catch (e) {}
                    banner.remove();
                });
            }
        })();
    </script>
@endif
