{{-- Shared show/hide password toggles. Wire any password input by placing a
     button with `data-password-toggle="<input-id>"` (containing .icon-eye and
     .icon-eye-off svgs) next to it, then @include this partial once per page. --}}
<script>
    (function () {
        document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
            const input = document.getElementById(btn.getAttribute('data-password-toggle'));
            if (!input) return;

            const eye = btn.querySelector('.icon-eye');
            const eyeOff = btn.querySelector('.icon-eye-off');

            btn.addEventListener('click', () => {
                const show = input.type === 'password';

                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');

                if (eye) eye.classList.toggle('hidden', show);
                if (eyeOff) eyeOff.classList.toggle('hidden', !show);
            });
        });
    })();
</script>
