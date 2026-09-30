/**
 * Confirmation dialog engine.
 *
 * Replaces every native `confirm()` box the CRUD actions used to raise
 * ("barangay-system-ochre.vercel.app says / Delete household HH-001?").
 *
 * Usage in Blade — the copy lives on the <form>:
 *
 *   <form method="POST" action="..."
 *         data-confirm="Delete household HH-001?"
 *         data-confirm-title="Delete household"
 *         data-confirm-accept="Delete"
 *         data-confirm-icon="trash"
 *         data-confirm-tone="danger">...</form>
 *
 *   tone:  "danger" (red, default) | "primary" (sky, reversible actions)
 *   icon:  "trash" | "x-circle" | "x-mark" | "archive-box" |
 *          "check-circle" | "envelope" | "cog-6-tooth" | "warning"
 *          (default) — one SVG in the card; the rest stay hidden.
 *
 * Copy rule: the header names the object, the message asks the
 * question, the buttons carry bare verbs ("Cancel" / "Remove").
 *
 * Element hooks inside the dialog markup use the data-confirm-dialog-*
 * namespace, so they can never collide with the form attributes above.
 *
 * Behaviour:
 *   - confirm re-submits the same form via requestSubmit(), so CSRF,
 *     method spoofing and validation are untouched;
 *   - without JS (or if the dialog markup is absent) the form submits
 *     normally — this is pure progressive enhancement;
 *   - Escape / Cancel / backdrop click all dismiss without submitting.
 */
(function () {
    'use strict';

    var dialog = null;
    var pendingForm = null;
    var lastFocused = null;
    var bypassForm = null;

    function node() {
        if (dialog === null) dialog = document.getElementById('confirm-dialog');
        return dialog;
    }

    function q(sel) {
        var d = node();
        return d ? d.querySelector(sel) : null;
    }

    // Every overlay that locks page scroll. The confirm card must stay aware
    // of the error/settings dialog stack: closing confirm must not unlock
    // the page, steal focus, or close a card that is still open above it.
    var OVERLAY_SELECTOR = '.crud-dialog:not(.hidden), #confirm-dialog:not(.hidden), #error-dialog:not(.hidden), #settings-dialog:not(.hidden), #forgot-modal:not(.hidden), #register-modal:not(.hidden)';

    function anyOverlayOpen() {
        return !!document.querySelector(OVERLAY_SELECTOR);
    }

    function topOverlay() {
        return document.querySelector('#settings-dialog:not(.hidden)')
            || document.querySelector('#error-dialog:not(.hidden)')
            || document.querySelector('#confirm-dialog:not(.hidden)')
            || document.querySelector('.crud-dialog:not(.hidden)')
            || document.querySelector('#forgot-modal:not(.hidden), #register-modal:not(.hidden)');
    }

    function scrollLock(on) {
        if (on) {
            document.body.classList.add('overflow-hidden');
            return;
        }
        // Another dialog may still be open — don't unlock the page under it.
        if (document.querySelector('.crud-dialog:not(.hidden)')) return;
        if (document.querySelector('#error-dialog:not(.hidden)')) return;
        if (document.querySelector('#settings-dialog:not(.hidden)')) return;
        if (document.querySelector('#forgot-modal:not(.hidden), #register-modal:not(.hidden)')) return;
        document.body.classList.remove('overflow-hidden');
    }

    function close() {
        var d = node();
        if (!d || d.classList.contains('hidden')) return;
        d.classList.add('hidden');
        scrollLock(false);
        pendingForm = null;
        var restore = lastFocused;
        lastFocused = null;
        if (anyOverlayOpen()) {
            // An error/settings card is still open — leave it in charge.
            var top = topOverlay();
            if (top && !top.contains(document.activeElement)) {
                var fallback = top.querySelector('button:not([disabled])') || top.querySelector('[tabindex="-1"]');
                if (fallback && fallback.focus) fallback.focus();
            }
            return;
        }
        if (restore && restore.focus && restore.isConnected) restore.focus();
    }

    function accept() {
        var form = pendingForm;
        close();
        if (!form) return;
        if (typeof form.requestSubmit === 'function') {
            bypassForm = form;
            form.requestSubmit(); // fires submit again; the guard below lets it through
            // If validation or another listener blocked the submit event, drop
            // the bypass — otherwise the *next* submit of this form would skip
            // the dialog and delete silently.
            if (bypassForm === form) bypassForm = null;
        } else {
            form.submit(); // legacy path: skips the submit event entirely
            bypassForm = null;
        }
    }

    function open(form, message) {
        var d = node();
        var acceptBtn = q('[data-confirm-dialog-accept]');
        if (!d || !acceptBtn) return false;

        pendingForm = form;
        lastFocused = document.activeElement;

        var primary = (form.getAttribute('data-confirm-tone') || 'danger') === 'primary';
        var key = primary ? 'data-classes-primary' : 'data-classes-danger';

        var title = q('[data-confirm-dialog-title]');
        if (title) title.textContent = form.getAttribute('data-confirm-title') || 'Are you sure?';

        var body = q('[data-confirm-dialog-message]');
        if (body) body.textContent = message;

        var icon = q('[data-confirm-dialog-icon]');
        if (icon) icon.className = icon.getAttribute(key) || icon.className;

        // Action-specific icon: data-confirm-icon picks one SVG, the rest
        // stay hidden. An unknown name falls back to the warning triangle
        // so the card never renders without one.
        var wanted = form.getAttribute('data-confirm-icon') || 'warning';
        var svgs = d.querySelectorAll('[data-confirm-icon-for]');
        var picked = null;
        svgs.forEach(function (svg) {
            var match = svg.getAttribute('data-confirm-icon-for') === wanted;
            svg.classList.toggle('hidden', !match);
            if (match) picked = svg;
        });
        if (!picked) {
            svgs.forEach(function (svg) {
                svg.classList.toggle('hidden', svg.getAttribute('data-confirm-icon-for') !== 'warning');
            });
        }

        acceptBtn.textContent = form.getAttribute('data-confirm-accept') || 'Confirm';
        acceptBtn.className = acceptBtn.getAttribute(key) || acceptBtn.className;

        var dismissBtn = q('[data-confirm-dialog-cancel]');
        if (dismissBtn) dismissBtn.textContent = form.getAttribute('data-confirm-dismiss') || 'Cancel';

        // Hold the 44px line shared with .btn (min-h-11): if a future class
        // edit drops the utility, the height must not drift.
        [dismissBtn, acceptBtn].forEach(function (btn) {
            if (btn && !btn.classList.contains('min-h-11')) btn.classList.add('min-h-11');
        });

        d.classList.remove('hidden');
        scrollLock(true);

        // Focus Cancel — Enter must not destroy a record by reflex — unless
        // an error/settings card is already open above us. Stealing focus
        // from it would bury the message the user is reading.
        var overlayOpen = document.querySelector('#error-dialog:not(.hidden), #settings-dialog:not(.hidden)');
        if (!overlayOpen) (dismissBtn || acceptBtn).focus();
        return true;
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || form.tagName !== 'FORM') return;
        if (form === bypassForm) {
            bypassForm = null;
            return;
        }
        var message = form.getAttribute('data-confirm');
        if (!message) return;
        if (!node()) return; // dialog not rendered on this page — submit normally
        if (open(form, message)) event.preventDefault();
    });

    document.addEventListener('click', function (event) {
        var d = node();
        if (!d || d.classList.contains('hidden')) return;
        var target = event.target;
        if (!target || !target.closest) return;

        if (target.closest('[data-confirm-dialog-accept]')) {
            event.preventDefault();
            accept();
            return;
        }
        if (target.closest('[data-confirm-dialog-cancel]')) {
            event.preventDefault();
            close();
            return;
        }
        // Click on the dim backdrop (anything outside the panel) dismisses.
        var panel = d.querySelector('.confirm-panel');
        if (panel && !panel.contains(target)) close();
    });

    // Capture phase: crud-dialogs registers its own Escape/Tab handler on
    // document in the bubble phase, and listeners run in registration order
    // (it is imported first). Capturing lets stopPropagation() below actually
    // keep an open CRUD dialog from closing underneath the confirmation.
    document.addEventListener('keydown', function (event) {
        var d = node();
        if (!d || d.classList.contains('hidden')) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation(); // don't also close an open CRUD dialog
            close();
            return;
        }
        if (event.key !== 'Tab') return;

        var focusables = d.querySelectorAll('button:not([disabled])');
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
})();
