    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const modal = document.getElementById('forgot-modal');
            const backdrop = document.getElementById('forgot-backdrop');
            const stepEmail = document.getElementById('forgot-step-email');
            const stepCode = document.getElementById('forgot-step-code');
            const stepDone = document.getElementById('forgot-step-done');
            const boxes = Array.from(document.querySelectorAll('.forgot-code-box'));
            const codeField = document.getElementById('forgot-code');

            let cooldown = {{ $resetCooldown }};
            let cooldownTimer = null;
            let modalEmail = '';

            const el = (id) => document.getElementById(id);

            // ---------- dialog plumbing ----------
            function showStep(step) {
                [stepEmail, stepCode, stepDone].forEach((s) => s.classList.add('hidden'));
                step.classList.remove('hidden');
            }

            let lastFocused = null;

            function openModal() {
                // Prefill from the login form's email if the visitor already typed one.
                const loginEmail = el('email').value.trim();
                if (loginEmail && !el('forgot-email').value) {
                    el('forgot-email').value = loginEmail;
                }

                lastFocused = document.activeElement;
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                showStep(stepEmail);
                setTimeout(() => el('forgot-email').focus(), 60);
            }

            function closeModal() {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                if (lastFocused) lastFocused.focus();
            }

            // Keep Tab cycling inside the dialog while it is open.
            modal.addEventListener('keydown', (event) => {
                if (event.key !== 'Tab') return;

                const focusables = modal.querySelectorAll('button:not([disabled]), input:not([disabled]), a[href]');
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

            document.querySelectorAll('[data-open-forgot]').forEach((trigger) => {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    openModal();
                });
            });

            el('forgot-close').addEventListener('click', closeModal);
            backdrop.addEventListener('click', closeModal);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
            });

            // ---------- cooldown (server-authoritative, client-countdown) ----------
            function updateCooldownButtons() {
                const sendBtn = el('forgot-send-btn');
                const resendBtn = el('forgot-resend-btn');

                if (cooldown > 0) {
                    sendBtn.disabled = true;
                    sendBtn.textContent = `Wait ${cooldown}s to resend`;

                    resendBtn.disabled = true;
                    resendBtn.textContent = `Resend available in ${cooldown}s`;
                    resendBtn.classList.add('text-slate-500');
                    resendBtn.classList.remove('text-sky-700', 'hover:text-sky-700');
                } else {
                    sendBtn.disabled = false;
                    sendBtn.textContent = 'Send Reset Code';

                    resendBtn.disabled = false;
                    resendBtn.textContent = 'Send a new one';
                    resendBtn.classList.remove('text-slate-500');
                    resendBtn.classList.add('text-sky-700', 'hover:text-sky-700');
                }
            }

            function setCooldown(seconds) {
                cooldown = Math.max(0, Math.floor(seconds));
                if (cooldownTimer) clearInterval(cooldownTimer);
                updateCooldownButtons();

                if (cooldown > 0) {
                    cooldownTimer = setInterval(() => {
                        cooldown -= 1;
                        if (cooldown <= 0) {
                            cooldown = 0;
                            clearInterval(cooldownTimer);
                        }
                        updateCooldownButtons();
                    }, 1000);
                }
            }

            // Honest countdown straight after a page reload.
            setCooldown(cooldown);

            // ---------- helpers ----------
            // An inline error explains what just happened; it is not a state the
            // visitor has to act on later, so it retires on its own. 4.5s is the
            // toast duration, and the timer pauses on hover so nobody loses a
            // message they had started reading.
            const ERROR_DURATION = 4500;

            function scheduleDismiss(node) {
                if (node.dismissTimer) clearTimeout(node.dismissTimer);

                node.dismissTimer = setTimeout(() => {
                    node.dismissTimer = null;
                    node.textContent = '';
                    node.classList.add('hidden');
                }, ERROR_DURATION);
            }

            function showError(id, message) {
                const node = el(id);
                node.dismissPaused = false;
                node.textContent = message;
                node.classList.remove('hidden');
                scheduleDismiss(node);

                node.onmouseenter = () => {
                    if (!node.dismissTimer) return;
                    clearTimeout(node.dismissTimer);
                    node.dismissTimer = null;
                    node.dismissPaused = true;
                };
                node.onmouseleave = () => {
                    if (node.dismissPaused) {
                        node.dismissPaused = false;
                        scheduleDismiss(node);
                    }
                };
            }

            function clearErrors() {
                ['forgot-email-error', 'forgot-code-error', 'forgot-password-error'].forEach((id) => {
                    const node = el(id);
                    if (node.dismissTimer) clearTimeout(node.dismissTimer);
                    node.dismissTimer = null;
                    node.dismissPaused = false;
                    node.textContent = '';
                    node.classList.add('hidden');
                });
            }

            function maskEmail(email) {
                const at = email.indexOf('@');
                if (at <= 0) return '';
                const local = email.slice(0, at);
                const domain = email.slice(at + 1);
                const maskedLocal = local.length <= 2
                    ? local.slice(0, 1) + '*'.repeat(Math.max(local.length - 1, 2))
                    : local.slice(0, 2) + '*'.repeat(local.length - 2);
                return maskedLocal + '@' + domain;
            }

            const { syncCode, applyCode, readCodeFromClipboard } = createCodeBoxController(boxes, codeField);

            async function autoFillFromClipboard() {
                const text = await readCodeFromClipboard();

                return text ? applyCode(text) : false;
 }

            // Tab-regain focus: users copy the code from their mail client, then
            // switch back to the dialog — read once per focus gain, silently.
            let autoReadArmed = true;

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') autoReadArmed = true;
            });

            window.addEventListener('focus', async () => {
                if (!autoReadArmed || stepCode.classList.contains('hidden')) return;

                autoReadArmed = false;
                await autoFillFromClipboard();
            });

            // ---------- step 1: request a code ----------
            el('forgot-email-form').addEventListener('submit', async (event) => {
                event.preventDefault();
                const form = event.currentTarget;
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                clearErrors();

                const email = el('forgot-email').value.trim();
                const btn = el('forgot-send-btn');
                btn.disabled = true;
                btn.textContent = 'Sending…';

                try {
                    const response = await fetch('{{ route('password.email') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ email }),
                    });
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        showError('forgot-email-error', data.errors?.email?.[0] || data.message || 'Something went wrong. Please try again.');
                        if (data.cooldown === undefined) setCooldown(0); else setCooldown(data.cooldown);
                        return;
                    }

                    modalEmail = email;
                    el('forgot-email-held').value = email;
                    el('forgot-masked').textContent = maskEmail(email) || 'your email';
                    setCooldown(data.cooldown ?? 0);

                    codeField.value = '';
                    boxes.forEach((box) => (box.value = ''));
                    showStep(stepCode);

                    // The user may have the code on their clipboard already
                    // (copied from the email) — try a silent read.
                    await autoFillFromClipboard();

                    boxes[0].focus();
                } catch {
                    showError('forgot-email-error', 'Network error — check your connection and try again.');
                    setCooldown(0);
                } finally {
                    // Re-enabled unless a cooldown is running.
                    if (cooldown === 0) updateCooldownButtons();
                }
            });

            // ---------- step 2: verify code + set password ----------
            el('forgot-code-form').addEventListener('submit', async (event) => {
                event.preventDefault();
                const form = event.currentTarget;
                syncCode();
                if (codeField.value.length !== 6) {
                    showError('forgot-code-error', 'Enter the six-character reset code from your email.');
                    return;
                }
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                clearErrors();

                const btn = el('forgot-reset-btn');
                btn.disabled = true;
                btn.textContent = 'Resetting…';

                try {
                    const response = await fetch('{{ route('password.update') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({
                            email: el('forgot-email-held').value || modalEmail,
                            code: codeField.value,
                            password: el('forgot-password').value,
                            password_confirmation: el('forgot-password-confirmation').value,
                        }),
                    });
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        if (data.errors?.password?.[0]) {
                            showError('forgot-password-error', data.errors.password[0]);
                        } else {
                            showError('forgot-code-error', data.errors?.code?.[0] || data.message || 'That code is invalid or has expired.');
                        }
                        return;
                    }

                    // Success: server cleared the cooldown; show the confirmation.
                    setCooldown(0);
                    showStep(stepDone);
                } catch {
                    showError('forgot-code-error', 'Network error — check your connection and try again.');
                } finally {
                    btn.disabled = false;
                    btn.textContent = 'Reset Password';
                }
            });

            // ---------- resend ----------
            el('forgot-resend-btn').addEventListener('click', async () => {
                clearErrors();
                const email = el('forgot-email-held').value || modalEmail;
                if (!email) return;

                const btn = el('forgot-resend-btn');
                btn.disabled = true;
                btn.textContent = 'Sending…';

                try {
                    const response = await fetch('{{ route('password.email') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ email }),
                    });
                    const data = await response.json().catch(() => ({}));

                    if (data.cooldown !== undefined) setCooldown(data.cooldown);
                    boxes.forEach((box) => (box.value = ''));
                    codeField.value = '';
                    await autoFillFromClipboard();
                    boxes[0].focus();
                } finally {
                    if (cooldown === 0) updateCooldownButtons();
                }
            });

            // ---------- paste button (guaranteed path — a user gesture) ----------
            el('forgot-paste-btn').addEventListener('click', async () => {
                const text = await readCodeFromClipboard();

                if (text && applyCode(text)) return;

                // Nothing usable on the clipboard: fall back to focusing box 1
                // so a manual Ctrl+V lands in the boxes' own paste handler.
                boxes[0].focus();
            });

            // Show the button only where clipboard reading is actually possible.
            if (navigator.clipboard && window.isSecureContext) {
                el('forgot-paste-btn').classList.remove('hidden');
            }

            // ---------- done ----------
            el('forgot-done-btn').addEventListener('click', () => {
                // Carry the reset email into the login form so signing in is one step.
                if (modalEmail) {
                    el('email').value = modalEmail;
                    el('password').value = '';
                    el('password').focus();
                }
                closeModal();
            });

            // ---------- code box behavior (auto-advance, backspace, paste) ----------
            boxes.forEach((box, index) => {
                box.addEventListener('input', () => {
                    box.value = box.value.replace(/[^23456789ABCDEFGHJKMNPQRSTUVWXYZ]/gi, '').toUpperCase().slice(0, 1);
                    if (box.value && index < boxes.length - 1) boxes[index + 1].focus();
                    syncCode();
                });

                box.addEventListener('keydown', (event) => {
                    if (event.key === 'Backspace' && !box.value && index > 0) boxes[index - 1].focus();
                });

                box.addEventListener('paste', (event) => {
                    event.preventDefault();
                    const text = event.clipboardData.getData('text') || '';

                    // Strict token first ("your code is 343CVQ." → 343CVQ);
                    // fall back to the old strip-and-fill for fragmented codes
                    // like "343-CVQ".
                    if (applyCode(text)) return;

                    const stripped = text.replace(/[^23456789ABCDEFGHJKMNPQRSTUVWXYZ]/gi, '').toUpperCase().slice(0, boxes.length);

                    boxes.forEach((target, i) => {
                        target.value = stripped[i] ?? '';
                    });

                    boxes[Math.min(stripped.length, boxes.length - 1)].focus();
                    syncCode();
                });
            });
        })();
    </script>
