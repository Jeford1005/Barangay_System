/**
 * Error dialog engine — the red counterpart to confirm-dialog.js.
 *
 * Shown for client-side failures only (nothing the server has already
 * answered):
 *
 *   - file too large: a photo input carrying data-file-max-kb picks up
 *     an over-limit file, clears itself and opens the card with a
 *     single "Choose another file" action that refocuses the input;
 *   - upload failed: a CRUD submit that carried a file came back
 *     non-422 / 413 — see crud-dialogs.js;
 *   - network: fetch() itself threw (fragment load or save).
 *
 * Public API:
 *
 *   window.showErrorDialog({
 *     type: 'size' | 'upload' | 'network',
 *     title: 'Oops! File Too Large',
 *     message: '...',
 *     actionLabel: 'Choose another file',
 *     cancel: false,             // hide the Cancel button (single action)
 *     retry: function () {},     // runs silently AFTER the card closes
 *     dismiss: function () {},   // runs after Cancel / Esc / backdrop
 *   });
 *
 * Retry never re-opens itself: if the retried request fails again the
 * caller opens the card anew, so a broken retry can't loop blindly.
 *
 * Behaviour mirrors confirm-dialog.js: Escape and backdrop dismiss,
 * focus starts on Cancel when both buttons show, Tab is trapped, and
 * an open CRUD dialog stays open underneath (that's where the values
 * the user typed still live).
 */
(function () {
    'use strict';

    var dialog = null;
    var pendingRetry = null;
    var pendingDismiss = null;
    var lastFocused = null;

    function node() {
        if (dialog === null) dialog = document.getElementById('error-dialog');
        return dialog;
    }

    function q(sel) {
        var d = node();
        return d ? d.querySelector(sel) : null;
    }

    function scrollLock(on) {
        if (on) {
            document.body.classList.add('overflow-hidden');
            return;
        }
        // Another dialog may still be open — don't unlock the page under it.
        if (document.querySelector('.crud-dialog:not(.hidden)')) return;
        if (document.querySelector('.confirm-dialog:not(.hidden)')) return;
        document.body.classList.remove('overflow-hidden');
    }

    function close() {
        var d = node();
        if (!d || d.classList.contains('hidden')) return;
        d.classList.add('hidden');
        scrollLock(false);

        var retry = pendingRetry;
        var dismiss = pendingDismiss;
        pendingRetry = null;
        pendingDismiss = null;

        var restore = lastFocused;
        lastFocused = null;
        if (restore && restore.focus) restore.focus();

        if (typeof dismiss === 'function') dismiss();
        if (typeof retry === 'function') retry();
    }

    function open(options) {
        var d = node();
        if (!d) return false;

        options = options || {};
        lastFocused = document.activeElement;
        pendingRetry = typeof options.retry === 'function' ? options.retry : null;
        pendingDismiss = typeof options.dismiss === 'function' ? options.dismiss : null;

        // Icon: swap by type, falling back to the upload glyph for an
        // unknown name so the card never renders without one.
        var wanted = options.type || 'upload';
        var svgs = d.querySelectorAll('[data-error-dialog-icon-for]');
        var picked = null;
        svgs.forEach(function (svg) {
            var match = svg.getAttribute('data-error-dialog-icon-for') === wanted;
            svg.classList.toggle('hidden', !match);
            if (match) picked = svg;
        });
        if (!picked) {
            svgs.forEach(function (svg) {
                svg.classList.toggle('hidden', svg.getAttribute('data-error-dialog-icon-for') !== 'upload');
            });
        }

        var title = q('[data-error-dialog-title]');
        if (title) title.textContent = options.title || 'Something went wrong';

        var message = q('[data-error-dialog-message]');
        if (message) {
            message.textContent = options.message || '';
            message.classList.toggle('hidden', !options.message);
        }

        var actionBtn = q('[data-error-dialog-action]');
        if (actionBtn) actionBtn.textContent = options.actionLabel || 'Retry';

        var cancelBtn = q('[data-error-dialog-cancel]');
        var showCancel = options.cancel !== false;
        if (cancelBtn) cancelBtn.classList.toggle('hidden', !showCancel);

        d.classList.remove('hidden');
        scrollLock(true);

        // Focus Cancel when it shows — Esc and Enter must stay harmless;
        // a single-action card focuses its only button instead.
        var focusTarget = showCancel && cancelBtn ? cancelBtn : actionBtn;
        if (focusTarget) focusTarget.focus();
        return true;
    }

    window.showErrorDialog = function (options) {
        return open(options);
    };

    document.addEventListener('click', function (event) {
        var d = node();
        if (!d || d.classList.contains('hidden')) return;
        var target = event.target;
        if (!target || !target.closest) return;

        if (target.closest('[data-error-dialog-action]')) {
            event.preventDefault();
            close(); // closes first, then runs the retry silently
            return;
        }
        if (target.closest('[data-error-dialog-cancel]')) {
            event.preventDefault();
            close();
            return;
        }
        // Click on the dim backdrop (anything outside the panel) dismisses.
        var panel = d.querySelector('.error-dialog-panel');
        if (panel && !panel.contains(target)) close();
    });

    // Capture phase so an open CRUD dialog beneath doesn't also close on
    // Escape or fight over Tab (it registers on the bubble phase).
    document.addEventListener('keydown', function (event) {
        var d = node();
        if (!d || d.classList.contains('hidden')) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            close();
            return;
        }
        if (event.key !== 'Tab') return;

        var focusables = Array.prototype.filter.call(
            d.querySelectorAll('button:not([disabled])'),
            function (btn) { return !btn.classList.contains('hidden'); }
        );
        if (!focusables.length) return;
        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        var active = document.activeElement;

        if (event.shiftKey && (active === first || !d.contains(active))) {
            event.preventDefault();
            event.stopPropagation();
            last.focus();
        } else if (!event.shiftKey && (active === last || !d.contains(active))) {
            event.preventDefault();
            event.stopPropagation();
            first.focus();
        }
    }, true);

    // ---- File pre-check (runs before the form ever reaches the server) ----
    document.addEventListener('change', function (event) {
        var input = event.target;
        if (!input || input.tagName !== 'INPUT' || input.type !== 'file') return;

        var maxKb = parseInt(input.getAttribute('data-file-max-kb') || '', 10);
        if (!maxKb || maxKb < 1) return;

        var file = input.files && input.files[0];
        if (!file) return;

        if (file.size > maxKb * 1024) {
            input.value = ''; // never let the over-limit file ride along
            open({
                type: 'size',
                title: 'Oops! File Too Large',
                message: 'This file is ' + formatSize(file.size) + '. The limit is '
                    + formatSize(maxKb * 1024) + '. Choose a smaller file.',
                actionLabel: 'Choose another file',
                cancel: false,
                retry: function () { input.focus(); },
            });
        }
    });

    function formatSize(bytes) {
        if (bytes >= 1024 * 1024) {
            return (bytes / (1024 * 1024)).toFixed(1).replace(/\.0$/, '') + ' MB';
        }
        return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }
})();
