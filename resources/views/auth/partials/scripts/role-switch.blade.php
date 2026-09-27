{{-- Drives the role tabs and is the only thing that decides which role the
     server receives. The field starts with no `name`, so without scripting the
     <noscript> select is the single control that submits `user_type` and the
     two can never both post it. --}}
<script>
    (function () {
        var list = document.querySelector('[data-role-tabs]');
        if (!list) return;

        var tabs = Array.prototype.slice.call(list.querySelectorAll('[data-role-tab]'));
        if (!tabs.length) return;

        var field = document.getElementById('login-user-type');
        var summary = document.querySelector('[data-role-summary]');

        function select(tab, moveFocus) {
            var role = tab.getAttribute('data-role-tab');

            tabs.forEach(function (other) {
                var on = other === tab;
                other.setAttribute('aria-selected', on ? 'true' : 'false');
                // Roving tabindex: one stop for the whole group, arrows move
                // within it, which is what a tablist is expected to do.
                other.tabIndex = on ? 0 : -1;
            });

            if (field) {
                field.setAttribute('name', 'user_type');
                field.value = role;
            }

            if (summary) {
                summary.textContent = tab.getAttribute('data-role-can') || '';
            }

            // The sign-up route only means anything to a resident, so the
            // closing line follows the chosen role. Each copy declares the roles
            // it serves, so no copy can be left showing beside its counterpart.
            Array.prototype.slice.call(document.querySelectorAll('[data-role-copy]')).forEach(function (el) {
                var roles = (el.getAttribute('data-role-copy') || '').split(/\s+/);
                el.classList.toggle('hidden', roles.indexOf(role) === -1);
            });

            if (moveFocus) tab.focus();
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                select(tab, false);
            });

            tab.addEventListener('keydown', function (event) {
                var index = tabs.indexOf(event.currentTarget);
                var next = null;

                switch (event.key) {
                    case 'ArrowRight':
                    case 'ArrowDown':
                        next = tabs[(index + 1) % tabs.length];
                        break;
                    case 'ArrowLeft':
                    case 'ArrowUp':
                        next = tabs[(index - 1 + tabs.length) % tabs.length];
                        break;
                    case 'Home':
                        next = tabs[0];
                        break;
                    case 'End':
                        next = tabs[tabs.length - 1];
                        break;
                    default:
                        return;
                }

                event.preventDefault();
                select(next, true);
            });
        });

        // Normalise on load so the field, the summary and the tabs always agree
        // with the role the server last saw, whatever the markup was handed.
        var current = tabs.filter(function (tab) {
            return tab.getAttribute('aria-selected') === 'true';
        })[0] || tabs[0];

        select(current, false);
    })();
</script>
