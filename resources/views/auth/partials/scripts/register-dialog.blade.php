    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const modal = document.getElementById('register-modal');
            const backdrop = document.getElementById('register-backdrop');
            const stepForm = document.getElementById('register-step-form');
            const stepDone = document.getElementById('register-step-done');
            const form = document.getElementById('register-form');
            const submitBtn = document.getElementById('register-submit-btn');
            const stepFooter = document.getElementById('register-step-footer');

            let lastFocused = null;

            function showStep(step) {
                [stepForm, stepDone].forEach((s) => s.classList.add('hidden'));
                step.classList.remove('hidden');
                // The sticky footer belongs to the form step only.
                stepFooter.classList.toggle('hidden', step !== stepForm);
            }

            function openRegisterModal() {
                // Prefill from the login form's email if the visitor already typed one.
                const loginEmail = document.getElementById('email').value.trim();
                const regEmail = document.getElementById('register-email');
                if (loginEmail && regEmail && !regEmail.value) regEmail.value = loginEmail;

                lastFocused = document.activeElement;
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                showStep(stepForm);
                setTimeout(() => document.getElementById('register-first_name').focus(), 60);
            }

            function closeRegisterModal() {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                if (lastFocused) lastFocused.focus();
            }

            window.openRegisterModal = openRegisterModal;

            document.querySelectorAll('[data-open-register]').forEach((btn) => {
                btn.addEventListener('click', openRegisterModal);
            });

            document.getElementById('register-close').addEventListener('click', closeRegisterModal);
            backdrop.addEventListener('click', closeRegisterModal);
            document.getElementById('register-done-btn').addEventListener('click', closeRegisterModal);

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) closeRegisterModal();
            });

            // Keep Tab cycling inside the dialog while it is open.
            modal.addEventListener('keydown', (event) => {
                if (event.key !== 'Tab') return;

                const focusables = modal.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])');
                if (!focusables.length) return;

                const first = focusables[0];
                const last = focusables[focusables.length - 1];

                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });

            // ---------- inline field errors ----------
            function clearErrors() {
                form.querySelectorAll('.register-error').forEach((p) => p.remove());
                form.querySelectorAll('.border-red-500\\!').forEach((el) => el.classList.remove('border-red-500!'));
            }

            function showErrors(errors) {
                clearErrors();

                Object.entries(errors).forEach(([field, messages]) => {
                    const input = form.querySelector('[name="' + field + '"]');
                    if (!input) return;

                    input.classList.add('border-red-500!');

                    const p = document.createElement('p');
                    p.className = 'register-error mt-1 text-sm text-red-600';
                    p.setAttribute('role', 'alert');
                    p.textContent = Array.isArray(messages) ? messages[0] : messages;
                    input.insertAdjacentElement('afterend', p);
                });
            }

            // ---------- submit via fetch so the dialog never navigates away ----------
            // Failures open the shared red message box (the same card the app
            // shell uses) instead of a native alert(); its action re-runs this
            // exact submission with the fields already typed kept in place.
            function showErrorCard(options) {
                if (typeof window.showErrorDialog === 'function') {
                    window.showErrorDialog(options);
                    return true;
                }
                // Fallback only if the dialog markup is somehow missing.
                return false;
            }

            async function submitRegistration() {
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                clearErrors();

                submitBtn.disabled = true;
                const originalLabel = submitBtn.textContent;
                submitBtn.textContent = 'Submitting…';

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: new FormData(form),
                    });

                    if (res.ok) {
                        form.reset();
                        showStep(stepDone);
                        setTimeout(closeRegisterModal, 1800);
                        return;
                    }

                    if (res.status === 422) {
                        const data = await res.json();
                        showErrors(data.errors || {});
                        return;
                    }

                    showErrorCard({
                        type: 'warning',
                        title: 'Registration failed',
                        message: res.status === 429
                            ? 'Too many attempts. Please wait a minute, then try again.'
                            : 'Something went wrong. Please try again.',
                        actionLabel: 'Try Again',
                        retry: submitRegistration,
                    }) || alert('Something went wrong. Please try again.');
                } catch (err) {
                    showErrorCard({
                        type: 'network',
                        title: 'Network error',
                        message: 'Check your connection and try again. Your details are still here.',
                        actionLabel: 'Retry',
                        retry: submitRegistration,
                    }) || alert('Network error — please try again.');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalLabel;
                }
            }

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                submitRegistration();
            });

            // Arrived back here after a failed non-JS submission (or with old
            // input): reopen the dialog with the server-rendered errors shown.
            @if ($errors->register->any() || old('first_name'))
                openRegisterModal();
            @endif
        })();
    </script>
