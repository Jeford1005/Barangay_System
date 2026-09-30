{{-- Clears a rejected password attempt without replaying the password.
     Rendered only when the server reports a login error, so a failed attempt
     leaves the field empty and focused on the next paint. The message hides
     after 5s but stays announced: role="alert" implies aria-live="assertive",
     so screen readers finish speaking it, and focusing the field never
     dismisses it early. Hovering pauses the timer so sighted readers don't
     lose a message they started reading. --}}
@if ($errors->has('password'))
    <script>
        (function () {
            var field = document.getElementById('password');
            var error = document.getElementById('login-password-error');
            if (!field) return;

            field.value = '';
            field.focus();

            if (error) {
                error.setAttribute('role', 'alert');
                error.setAttribute('aria-live', 'assertive');
            }

            var timer = window.setTimeout(hide, 5000);

            function hide() {
                window.clearTimeout(timer);
                timer = null;
                if (!error) return;
                error.classList.add('hidden');
                field.classList.remove('border-red-500!', 'focus:border-red-500', 'focus:ring-red-500/20');
                field.removeAttribute('aria-invalid');
                field.removeAttribute('aria-describedby');
            }

            // Pause while the message is being read; never hide on focus —
            // moving into the field to retry must not erase the explanation.
            if (error) {
                error.addEventListener('mouseenter', function () {
                    if (timer) window.clearTimeout(timer);
                    timer = null;
                });
                error.addEventListener('mouseleave', function () {
                    if (!timer && !error.classList.contains('hidden')) {
                        timer = window.setTimeout(hide, 5000);
                    }
                });
            }
        })();
    </script>
@endif
