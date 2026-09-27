/**
 * Keep text inputs that represent numeric identifiers or phone numbers from
 * accepting letters in the browser. Server-side validation remains the source
 * of truth; this only gives immediate feedback while typing/pasting.
 */
(function () {
    'use strict';

    function sanitize(input) {
        if (input.matches('[data-digits-only="true"]')) {
            input.value = input.value.replace(/[^0-9]/g, '');
        }

        if (input.matches('[data-phone="true"]')) {
            input.value = input.value.replace(/[^0-9+()\-\s]/g, '');
        }
    }

    document.addEventListener('input', function (event) {
        if (event.target instanceof HTMLInputElement) {
            sanitize(event.target);
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target instanceof HTMLInputElement) {
            sanitize(event.target);
        }
    });
})();
