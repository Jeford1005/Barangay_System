{{-- Clears a rejected password attempt without replaying the password.
     Rendered only when the server reports a login error, so a failed attempt
     leaves the field empty and focused on the next paint. --}}
@if ($errors->has('password'))
    <script>
        (function () {
            var field = document.getElementById('password');
            var error = document.getElementById('login-password-error');
            if (!field) return;

            field.value = '';
            field.focus();

            window.setTimeout(function () {
                if (!error) return;
                error.classList.add('hidden');
                field.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500/20');
                field.removeAttribute('aria-invalid');
                field.removeAttribute('aria-describedby');
                field.blur();
            }, 5000);
        })();
    </script>
@endif
