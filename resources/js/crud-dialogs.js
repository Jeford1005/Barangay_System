/**
 * CRUD dialog engine.
 *
 * Dialogs are declared in Blade with <x-crud-dialog> and opened with
 * [data-dialog-open] anchors. Form markup is injected from the existing
 * create/edit pages (fetched with ?fragment=1 — those views return only
 * the bare form card in fragment mode), then submitted via fetch with
 * Accept: application/json so the dialog never navigates away:
 *
 *   - 422: field errors are rendered client-side (red border + message
 *     under each field) — typed values are preserved, exactly like the
 *     login-page create-account dialog.
 *   - success (redirect followed): the dialog closes, the list reloads
 *     and the server flash renders as a toast. If the dialog declares
 *     data-open-on-success (certificates), the redirect target (the
 *     print sheet) opens in a new tab first.
 *
 * <a href> triggers keep working without JS: the engine preventDefaults
 * only when it actually opens the dialog.
 */
(function () {
    'use strict';

    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var openDialog = null;
    var lastFocused = null;

    function body(dialog) {
        return dialog.querySelector('.crud-dialog-body');
    }

    function showSkeleton(dialog) {
        var sk = dialog.querySelector('[data-skeleton]');
        var b = body(dialog);
        b.querySelectorAll(':scope > :not([data-skeleton])').forEach(function (n) { n.remove(); });
        if (sk) sk.classList.remove('hidden');
    }

    function hideSkeleton(dialog) {
        dialog.querySelector('[data-skeleton]')?.classList.add('hidden');
    }

    function focusFirstField(dialog) {
        var field = body(dialog)?.querySelector('input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled])');
        if (field) field.focus();
    }

    function open(dialog, fetchUrl, mode) {
        if (openDialog && openDialog !== dialog) close(openDialog);

        openDialog = dialog;
        lastFocused = document.activeElement;
        dialog.classList.remove('hidden');
        dialog.setAttribute('aria-busy', 'true');
        document.body.classList.add('overflow-hidden');
        var dialogBody = body(dialog);
        if (dialogBody) dialogBody.scrollTop = 0;
        dialog.querySelector('.crud-dialog-panel')?.focus();

        var url = fetchUrl || dialog.dataset.fetchBase;
        if (url && dialog.dataset.loadedUrl !== url) {
            loadFragment(dialog, url);
        } else {
            dialog.removeAttribute('aria-busy');
            focusFirstField(dialog);
        }
    }

    function close(dialog) {
        dialog.classList.add('hidden');
        dialog.removeAttribute('aria-busy');
        document.body.classList.remove('overflow-hidden');
        if (lastFocused?.getAttribute('aria-controls') === dialog.id) {
            lastFocused.setAttribute('aria-expanded', 'false');
        }
        if (openDialog === dialog) openDialog = null;
        if (lastFocused && lastFocused.focus) lastFocused.focus();
    }

    /**
     * GET the form page with ?fragment=1 and swap the returned form card
     * into the dialog body. The fragment includes its own <form> markup
     * (CSRF, method spoofing, model values), so submission semantics are
     * identical to the standalone pages. Inline <script> blocks inside the
     * fragment (e.g. the certificate fee sync) are re-created so they run.
     */
    function loadFragment(dialog, url) {
        showSkeleton(dialog);

        var sep = url.indexOf('?') === -1 ? '?' : '&';
        return fetch(url + sep + 'fragment=1', {
            headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(function (html) {
                var b = body(dialog);
                b.querySelectorAll(':scope > :not([data-skeleton])').forEach(function (n) { n.remove(); });

                // The response may be the bare fragment card OR a full HTML
                // document (when fragment mode is bypassed, e.g. after a
                // session change). Parse in a template: head meta elements
                // get hoisted as first children, so locate the fragment
                // wrapper explicitly instead of trusting firstElementChild.
                var tpl = document.createElement('template');
                tpl.innerHTML = html.trim();
                var node = tpl.content.querySelector('[data-crud-fragment]');
                if (!node) {
                    // No wrapper found: take the first element that carries a form.
                    node = tpl.content.querySelector('form')?.closest('div');
                }
                if (!node) throw new Error('fragment not found');

                // Unwrap: move the card itself (not the wrapper) into the body.
                var card = node.firstElementChild;
                b.appendChild(card || node);
                dialog.dataset.loadedUrl = url;
                hideSkeleton(dialog);

                // Scripts injected via innerHTML never execute — re-create them.
                b.querySelectorAll('script').forEach(function (old) {
                    var s = document.createElement('script');
                    if (old.src) s.src = old.src;
                    s.textContent = old.textContent;
                    old.replaceWith(s);
                });

                wireForm(dialog, (card || node).querySelector('form') || (card || node));
                dialog.removeAttribute('aria-busy');
                focusFirstField(dialog);
            })
            .catch(function () {
                // Hard fallback: navigate to the full page so nothing is lost.
                window.location.href = url;
            });
    }

    function wireForm(dialog, form) {
        if (!form || form.tagName !== 'FORM') return;

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            // Fetch-based dialogs bypass the browser's automatic submit
            // validation. Run it explicitly before sending the request.
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            var btn = form.querySelector('button[type="submit"]');
            var label = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

            fetch(form.action, {
                method: 'POST', // method spoofing via the _method field
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                body: new FormData(form),
            })
                .then(function (res) {
                    if (res.redirected || res.ok) {
                        success(dialog, res);
                        return null;
                    }
                    if (res.status === 422) {
                        return res.json().then(function (data) {
                            showErrors(form, data.errors || {});
                            window.showToast?.(firstError(data.errors) || 'Please review the highlighted fields.', 'error');
                        });
                    }
                    return res.json().catch(function () { return {}; }).then(function (data) {
                        window.showToast?.(data.message || 'Something went wrong. Please try again.', 'error');
                    });
                })
                .catch(function () {
                    window.showToast?.('Network error — please try again.', 'error');
                })
                .finally(function () {
                    if (btn) { btn.disabled = false; btn.textContent = label; }
                });
        });
    }

    function firstError(errors) {
        var k = errors && Object.keys(errors)[0];
        return k ? errors[k][0] : null;
    }

    /**
     * Client-side field errors (same pattern as the login register dialog):
     * red border on the input + message right below it. Typed values stay.
     */
    function showErrors(form, errors) {
        form.querySelectorAll('.crud-error').forEach(function (p) { p.remove(); });
        form.querySelectorAll('.border-red-400').forEach(function (el) { el.classList.remove('border-red-400'); });
        form.querySelectorAll('[aria-invalid="true"]').forEach(function (el) {
            el.removeAttribute('aria-invalid');
            el.removeAttribute('aria-describedby');
        });

        var firstInvalid = null;
        Object.entries(errors).forEach(function (entry) {
            var field = entry[0];
            var input = form.querySelector('[name="' + field + '"]:not([type="hidden"])');
            if (!input) return;

            input.classList.add('border-red-400');
            input.setAttribute('aria-invalid', 'true');
            var errorId = (input.id || field) + '-error';
            input.setAttribute('aria-describedby', errorId);
            if (!firstInvalid) firstInvalid = input;

            var p = document.createElement('p');
            p.id = errorId;
            p.className = 'crud-error mt-1.5 text-sm text-red-600';
            p.textContent = Array.isArray(entry[1]) ? entry[1][0] : entry[1];
            (input.closest('label') || input).insertAdjacentElement('afterend', p);
        });

        firstInvalid?.focus();
    }

    function extractToastMessage(html) {
        try {
            var parsed = new DOMParser().parseFromString(html, 'text/html');
            var message = parsed.querySelector('#toast-region .toast p')?.textContent?.trim();
            return message || 'Changes saved successfully.';
        } catch (error) {
            return 'Changes saved successfully.';
        }
    }

    function persistToast(message) {
        try {
            sessionStorage.setItem('crud-toast', JSON.stringify({
                message: message,
                type: 'success',
            }));
        } catch (error) {
            // Storage may be disabled; the normal server flash remains the fallback.
        }
    }

    function success(dialog, res) {
        var finalUrl = res && res.url ? res.url : null;
        var response = res && typeof res.clone === 'function' ? res.clone() : null;
        var messagePromise = response
            ? response.text().then(extractToastMessage).catch(function () { return 'Changes saved successfully.'; })
            : Promise.resolve('Changes saved successfully.');

        return messagePromise.then(function (message) {
            persistToast(message);

            close(dialog);
            dialog.dataset.loadedUrl = '';

            // Certificates: the store action redirects to the print sheet —
            // open it (fresh tab) so the clerk can print, then refresh the list.
            if (dialog.dataset.openOnSuccess && finalUrl) {
                window.open(finalUrl, '_blank');
            }

            window.location.reload();
        });
    }

    // ---------- global wiring ----------
    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-dialog-open]');
        if (opener) {
            var dialog = document.getElementById(opener.getAttribute('data-dialog-open'));
            if (dialog) {
                event.preventDefault();
                opener.setAttribute('aria-haspopup', 'dialog');
                opener.setAttribute('aria-controls', dialog.id);
                opener.setAttribute('aria-expanded', 'true');
                open(dialog, opener.dataset.fetchUrl || null, opener.dataset.fetchMode || null);
            }
            return;
        }

        if (openDialog) {
            if (event.target.closest('[data-dialog-close]')) {
                event.preventDefault();
                close(openDialog);
                return;
            }
            if (event.target.classList.contains('crud-dialog-backdrop')) {
                close(openDialog);
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && openDialog) close(openDialog);
        if (event.key === 'Tab' && openDialog) {
            var focusables = openDialog.querySelectorAll('button:not([disabled]), input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])');
            if (!focusables.length) return;
            var first = focusables[0];
            var last = focusables[focusables.length - 1];
            var active = document.activeElement;
            if (!openDialog.contains(active)) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus();
            } else if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    // Reopening "create" after viewing an edit must restore the create form.
    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-dialog-open][data-fetch-mode="create"]');
        if (opener) {
            var dialog = document.getElementById(opener.getAttribute('data-dialog-open'));
            if (dialog) dialog.dataset.loadedUrl = '';
        }
    }, true);

    // Deep links: arriving at an index with ?open=<dialog-suffix> opens that
    // dialog immediately (dashboard quick actions). The suffix matches the
    // dialog id prefix, e.g. ?open=resident -> #resident-dialog.
    var wanted = new URLSearchParams(window.location.search).get('open');
    if (wanted) {
        var target = document.getElementById(wanted + '-dialog');
        if (target) {
            // Strip the query so a later reload doesn't re-open the dialog.
            window.history.replaceState({}, '', window.location.pathname);
            open(target, null, 'create');
        }
    }
})();
