/**
 * Toast + asset-load hardening (JS-only).
 *
 * The toast markup and its inline script live in Blade (x-toasts), which this
 * module must not edit. It hardens behaviour from the outside:
 *
 *   - single dismiss path: dismiss() is guarded, so the inline onclick, the
 *     wired click listener, the auto-dismiss timer and the animation fallback
 *     can never remove a toast twice;
 *   - auto-dismiss timer with hover-pause: remaining time is tracked, the
 *     timer pauses on mouseenter and resumes on mouseleave;
 *   - fallback only if primary fails: the safety timeout removes the node
 *     only when it is still connected (i.e. animationend never fired);
 *   - capped hover extension: a hard deadline timer (set once, never paused
 *     by hover) retires the toast even if it is hovered repeatedly.
 *
 * Single Tailwind load path: the auth pages and app-layout each carry the
 * same vite-or-CDN conditional. If both ever render (or the Play CDN script
 * is injected twice), keep exactly one source — prefer the built CSS and
 * drop the CDN script, otherwise keep the first CDN script and drop extras.
 */
(function () {
    'use strict';

    function guardTailwindLoad() {
        try {
            var cdn = Array.prototype.slice.call(
                document.querySelectorAll('script[src*="cdn.tailwindcss.com"]')
            );
            if (!cdn.length) return;
            var hasBuiltCss = !!document.querySelector('link[href*="build/assets"]');
            if (hasBuiltCss) {
                cdn.forEach(function (s) { s.remove(); });
                return;
            }
            cdn.slice(1).forEach(function (s) { s.remove(); });
        } catch (error) {
            // Cosmetic only — never break the page over asset hygiene.
        }
    }

    var DURATION = 4500; // matches the Blade auto-dismiss duration
    var HOVER_REJOIN = 2000; // re-arm delay after the pointer leaves
    var MAX_EXTEND = 6000; // cap: hovering can never extend a toast past DURATION + this
    var FALLBACK_MS = 400;

    // Single dismiss path — every caller funnels through here.
    function dismiss(toast) {
        if (!toast || toast.dataset.dismissed === '1' || !toast.isConnected) return;
        toast.dataset.dismissed = '1';
        if (toast._toastTimer) {
            clearTimeout(toast._toastTimer);
            toast._toastTimer = null;
        }
        toast.classList.add('toast-leaving');
        var done = false;
        function remove() {
            if (done) return;
            done = true;
            if (toast.isConnected) toast.remove();
        }
        toast.addEventListener('animationend', remove, { once: true });
        // Fallback only if the primary (animationend) path never fires
        // (e.g. reduced motion) — and only while the node is still there.
        setTimeout(function () {
            if (toast.isConnected) remove();
        }, FALLBACK_MS);
    }

    function arm(toast, delay) {
        if (toast._toastTimer) clearTimeout(toast._toastTimer);
        if (toast._toastDeadline === undefined) {
            toast._toastDeadline = Date.now() + DURATION + MAX_EXTEND;
        }
        var wait = Math.min(delay, Math.max(0, toast._toastDeadline - Date.now()));
        if (wait <= 0) {
            dismiss(toast);
            return;
        }
        toast._toastTimer = setTimeout(function () {
            dismiss(toast);
        }, wait);
    }

    function harden(toast) {
        if (!toast || toast.dataset.hardened === '1') return;
        toast.dataset.hardened = '1';
        // Neutralize the inline onclick path so dismissal flows through
        // dismiss() exactly once instead of racing it with .remove().
        toast.querySelectorAll('[onclick]').forEach(function (el) {
            el.removeAttribute('onclick');
        });
        toast
            .querySelector('button[aria-label="Dismiss"]')
            ?.addEventListener('click', function () {
                dismiss(toast);
            });
        // Hover-pause: freeze the countdown while the pointer is over the
        // toast, resume with the capped re-arm delay when it leaves.
        toast.addEventListener('mouseenter', function () {
            if (toast._toastTimer) {
                clearTimeout(toast._toastTimer);
                toast._toastTimer = null;
            }
        });
        toast.addEventListener('mouseleave', function () {
            if (toast.dataset.dismissed !== '1') arm(toast, HOVER_REJOIN);
        });
        // Hard deadline: set once, never paused by hover — caps total life
        // even under repeated hover, including timers armed by older scripts.
        setTimeout(function () {
            dismiss(toast);
        }, DURATION + MAX_EXTEND);
        arm(toast, DURATION);
    }

    function hardenAll() {
        var region = document.getElementById('toast-region');
        if (!region) return;
        region.querySelectorAll('.toast').forEach(harden);
    }

    // Same signature as the Blade API; hardened single-dismiss + capped hover.
    function installShowToast() {
        var region = document.getElementById('toast-region');
        if (!region) return;
        window.showToast = function (message, type) {
            var colors = {
                success: 'border-emerald-200',
                error: 'border-red-200',
                warning: 'border-amber-200',
            };
            var el = document.createElement('div');
            el.className =
                'toast pointer-events-auto w-full flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg backdrop-blur bg-white/95 ' +
                (colors[type] || 'border-slate-200');
            el.setAttribute('role', 'status');
            el.innerHTML =
                '<p class="flex-1 text-sm font-medium text-slate-800"></p>' +
                '<button type="button" class="shrink-0 rounded-md p-1 text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none" aria-label="Dismiss">' +
                '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>' +
                '</button>';
            el.querySelector('p').textContent = message;
            region.appendChild(el);
            harden(el);
        };
        // If the Blade inline script already replayed the pending crud-toast
        // it consumed the key; replay here only when it is still waiting.
        try {
            var pending = JSON.parse(sessionStorage.getItem('crud-toast') || 'null');
            if (pending && pending.message) {
                sessionStorage.removeItem('crud-toast');
                window.showToast(pending.message, pending.type || 'success');
            }
        } catch (error) {
            // Ignore malformed or unavailable session storage.
        }
    }

    guardTailwindLoad();
    hardenAll();
    installShowToast();
})();
