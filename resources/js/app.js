import './bootstrap';

/**
 * Forms tagged with [data-submit-loading] lock their submit button while the
 * request is in flight, so a double click can't fire a second submission.
 */
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches('[data-submit-loading]')) {
        return;
    }

    if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
        return;
    }

    const button = form.querySelector('button[type="submit"]');

    if (!button || button.disabled) {
        return;
    }

    button.dataset.label ??= button.textContent.trim();
    button.disabled = true;
    button.textContent = button.dataset.loadingLabel || 'Signing in…';
});

/**
 * Show / hide toggle for password fields: [data-toggle-password="<input id>"].
 */document.addEventListener('click', (event) => {
    const toggle = event.target instanceof Element ? event.target.closest('[data-toggle-password]') : null;

    if (!toggle) {
        return;
    }

    const input = document.getElementById(toggle.getAttribute('data-toggle-password'));

    if (!input || !(input instanceof HTMLInputElement)) {
        return;
    }

    const revealing = input.type === 'password';

    input.type = revealing ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(revealing));
    toggle.setAttribute('aria-label', revealing ? 'Hide password' : 'Show password');

    toggle.querySelector('.password-eye')?.classList.toggle('hidden', revealing);
    toggle.querySelector('.password-eye-slash')?.classList.toggle('hidden', !revealing);

    input.focus({ preventScroll: true });
});

/**
 * Modal dialogs:
 *   [data-open-modal="<id>"]  opens the dialog with that id
 *   [data-modal]              the dialog itself (starts hidden)
 *   [data-close-modal]        closes the nearest open dialog
 *   [data-open-on-load]       opens as soon as the page is ready
 *
 * The panel is centered on screen by the markup; this handles behavior only:
 * Esc / backdrop click to close, focus moved into the dialog, Tab trapped
 * inside it, background scroll locked, and focus restored on close.
 */
let modalTrigger = null;

function openModal(modal, trigger) {
    if (!modal || !modal.classList.contains('hidden')) {
        return;
    }

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    modalTrigger = trigger || document.activeElement;

    const firstField = modal.querySelector('input:not([type="hidden"]), button:not([data-close-modal]), a[href]');
    (firstField || modal.querySelector('button'))?.focus({ preventScroll: true });
}

function closeModal(modal) {
    if (!modal) {
        return;
    }

    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';

    if (modalTrigger instanceof HTMLElement) {
        modalTrigger.focus({ preventScroll: true });
    }
    modalTrigger = null;
}

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const opener = event.target.closest('[data-open-modal]');
    if (opener) {
        event.preventDefault();
        openModal(document.getElementById(opener.getAttribute('data-open-modal')), opener);
        return;
    }

    const closer = event.target.closest('[data-close-modal]');
    if (closer) {
        closeModal(closer.closest('[data-modal]'));
    }
});

document.addEventListener('keydown', (event) => {
    const open = document.querySelector('[data-modal]:not(.hidden)');
    if (!open) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        closeModal(open);
        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const focusables = Array.from(
        open.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select, textarea'),
    ).filter((el) => el.offsetParent !== null);

    if (!focusables.length) {
        return;
    }

    const first = focusables[0];
    const last = focusables[focusables.length - 1];

    if (!open.contains(document.activeElement)) {
        event.preventDefault();
        first.focus();
    } else if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});

document.querySelectorAll('[data-modal][data-open-on-load]').forEach((modal) => openModal(modal));
