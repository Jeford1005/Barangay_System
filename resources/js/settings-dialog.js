/**
 * Settings dialog engine.
 *
 * Opens the settings module as a pop-up dialog (mock-style) instead of
 * navigating away, using progressive enhancement only:
 *
 *   - the sidebar's Settings link keeps its real href, so without JS (or on
 *     a deep link) /admin/settings still renders as a full page;
 *   - the pane is a same-origin iframe pointing at the section URL with
 *     ?embed=1, which strips the app chrome but keeps scripts, toasts and
 *     the confirm/error dialogs, so forms, filters and downloads inside the
 *     pane behave exactly as they do on the page;
 *   - the left nav is one Blade component (x-settings-nav), so the section
 *     list cannot drift from the routes it links;
 *
 * Element hooks use the data-settings-dialog-* namespace so they can never
 * collide with the form attributes the confirm engine reads.
 *
 * Behaviour: backdrop / × / Escape all close; focus is trapped while open
 * and restored to the trigger on close; the active section follows the
 * iframe's location, and a Escape pressed inside the frame closes the whole
 * dialog only when no inner dialog is showing (the inner confirm/error
 * engines swallow their own Escape first).
 */
(function () {
    'use strict';

    var dialog = null;
    var frame = null;
    var lastFocused = null;

    function node() {
        if (dialog === null) dialog = document.getElementById('settings-dialog');
        return dialog;
    }

    function frameNode() {
        if (frame === null) {
            var d = node();
            frame = d ? d.querySelector('[data-settings-frame]') : null;
        }
        return frame;
    }

    function isOpen() {
        var d = node();
        return !!d && !d.classList.contains('hidden');
    }

    function scrollLock(on) {
        if (on) {
            document.body.classList.add('overflow-hidden');
            return;
        }
        // Another dialog may still be open — don't unlock the page under it.
        if (document.querySelector('.crud-dialog:not(.hidden), #confirm-dialog:not(.hidden), #error-dialog:not(.hidden)')) return;
        document.body.classList.remove('overflow-hidden');
    }

    function embedUrl(href) {
        try {
            var url = new URL(href, window.location.origin);
            url.searchParams.set('embed', '1');
            return url.toString();
        } catch (e) {
            return href;
        }
    }

    function pathOf(href) {
        try {
            return new URL(href, window.location.origin).pathname;
        } catch (e) {
            return '';
        }
    }

    // Follow the iframe: highlight the longest nav entry that prefixes the
    // shown path, so /admin/users/5 keeps "User Accounts" active and
    // /admin/settings/maintenance stays on System Maintenance.
    function markActive(path) {
        var d = node();
        if (!d || !path) return;
        var links = d.querySelectorAll('nav a[href]');
        var best = null;
        links.forEach(function (a) {
            var p = pathOf(a.getAttribute('href'));
            if (!p) return;
            var hit = path === p || path.indexOf(p + '/') === 0;
            if (hit && (!best || p.length > pathOf(best.getAttribute('href')).length)) best = a;
        });
        links.forEach(function (a) {
            var active = a === best;
            a.classList.toggle('settings-tab-active', active);
            if (active) a.setAttribute('aria-current', 'page');
            else a.removeAttribute('aria-current');
        });

        // The pane header names the section the frame is showing.
        var title = d.querySelector('[data-settings-dialog-section]');
        if (title && best) title.textContent = best.getAttribute('data-section-label') || title.textContent;
    }

    function close() {
        var d = node();
        if (!d || d.classList.contains('hidden')) return;
        d.classList.add('hidden');
        scrollLock(false);
        var restore = lastFocused;
        lastFocused = null;
        if (restore && restore.focus && restore.isConnected) restore.focus();
    }

    function open(href) {
        var d = node();
        var f = frameNode();
        if (!d || !f) return false;

        lastFocused = document.activeElement;

        f.src = embedUrl(href); // reloads the frame at the section the trigger names
        d.classList.remove('hidden');
        scrollLock(true);
        markActive(pathOf(href));

        // Focus the active section — Enter there reopens what the user sees.
        var first = d.querySelector('nav a.settings-tab-active') || d.querySelector('nav a');
        if (first) first.focus();
        return true;
    }

    function loadSection(href, link) {
        var f = frameNode();
        if (!f) return;
        f.src = embedUrl(href);
        markActive(pathOf(href));
        if (link && link.focus) link.focus();
    }

    // Keep the frame's own document wired to the outer dialog.
    function bindFrame() {
        var f = frameNode();
        if (!f) return;
        f.addEventListener('load', function () {
            var doc;
            try {
                doc = f.contentDocument;
                if (!doc || !doc.location) return;
            } catch (e) {
                return; // not our origin — nothing to sync
            }

            markActive(doc.location.pathname);

            // Escape inside the frame closes the dialog, but only when the
            // frame shows no dialog of its own: the inner engines capture
            // Escape first, and crud-dialogs' bubble handler must still see
            // the key when a CRUD dialog is open (we check before it runs).
            doc.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape' || !isOpen()) return;
                if (doc.querySelector('.crud-dialog:not(.hidden), #confirm-dialog:not(.hidden), #error-dialog:not(.hidden)')) return;
                event.preventDefault();
                event.stopPropagation();
                close();
            }, true);
        });
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || !target.closest) return;

        var trigger = target.closest('[data-settings-open]');
        if (trigger) {
            // Modified clicks (new tab, window) fall through to the real href.
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
            if (!open(trigger.getAttribute('href'))) return; // no dialog here — navigate normally
            event.preventDefault();
            return;
        }

        if (!isOpen()) return;

        if (target.closest('[data-settings-dialog-close], [data-settings-dialog-backdrop]')) {
            event.preventDefault();
            close();
            return;
        }

        var navLink = target.closest('[data-settings-dialog] nav a');
        if (navLink) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
            event.preventDefault();
            loadSection(navLink.getAttribute('href'), navLink);
        }
    });

    // Capture phase, registered after confirm-dialog's handler: while a
    // confirmation is open it consumes Escape (and stops propagation) before
    // this runs, so the two can never close each other.
    document.addEventListener('keydown', function (event) {
        if (!isOpen()) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation(); // don't also reach crud-dialogs' bubble handler
            close();
            return;
        }
        if (event.key !== 'Tab') return;

        var d = node();
        // Every focusable control participates in the trap — buttons, links,
        // the frame, and all form fields (inputs incl. non-hidden, selects,
        // textareas) so Tab can never escape into the page behind the dialog.
        var focusables = d.querySelectorAll('button:not([disabled]), a[href], iframe, input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
        if (!focusables.length) return;
        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        var active = document.activeElement;

        if (event.shiftKey && (active === first || !d.contains(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (active === last || !d.contains(active))) {
            event.preventDefault();
            first.focus();
        }
    }, true);

    bindFrame(); // frame exists at parse time; bind once
})();
