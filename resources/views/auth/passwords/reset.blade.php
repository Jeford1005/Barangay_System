<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Enter Reset Code - {{ config('app.name', 'Barangay Management System') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/bidduang-mark.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- No built assets: fall back to the Tailwind Play CDN so the page renders without a build step. --}}
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
@include('components.bare-url')
</head>
<body class="min-h-dvh bg-slate-200 text-slate-900 antialiased">
    {{-- The seal, oversized and held at a whisper — the same backdrop the
         sign-in page opens on, so every unauthenticated route reads as one
         system. Fixed, so it stays put when the form scrolls. --}}
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 z-0 flex items-center justify-center overflow-hidden">
        <img src="{{ asset('images/bidduang-seal-circle.png') }}" alt=""
            class="h-auto w-[min(150vmin,1150px)] max-w-none select-none opacity-[0.11]">
    </div>

    <main class="relative z-10 flex min-h-dvh items-center justify-center px-4 py-3 sm:px-6">
        <div class="mx-auto w-full max-w-md">
            {{-- Card --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/5">
                {{-- Card header --}}
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-600 shadow-sm">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-base font-semibold tracking-tight text-slate-900">Check your email</h1>
                            <p class="text-xs text-slate-500">Step 2 of 2 · code &amp; new password</p>
                        </div>
                    </div>
                    <span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700">2 / 2</span>
                </div>

                <div class="px-6 py-5">
                    @php
                        // Mask the address: keep the first 1–2 characters of the local
                        // part and stars for the rest, e.g. ad***@gmail.com.
                        [$local, $domain] = array_pad(explode('@', $email), 2, '');
                        $localLength = mb_strlen($local);
                        $maskedLocal = $localLength <= 2
                            ? mb_substr($local, 0, 1).str_repeat('*', max($localLength - 1, 2))
                            : mb_substr($local, 0, 2).str_repeat('*', $localLength - 2);
                        $maskedEmail = trim($maskedLocal.($domain !== '' ? '@'.$domain : ''));
                    @endphp

                    <p class="text-sm text-slate-500">
                        Code sent to
                        @if ($maskedEmail !== '')
                            <span class="font-medium text-slate-800">{{ $maskedEmail }}</span>.
                        @else
                            your email.
                        @endif
                        Expires in {{ max($expiresInMinutes, 1) }} {{ $expiresInMinutes === 1 ? 'minute' : 'minutes' }}.
                    </p>

                    <form id="reset-form" method="POST" action="{{ route('password.update') }}" class="mt-4 space-y-4">
                        @csrf

                        <input type="hidden" name="email" value="{{ $email }}">

                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label class="block text-sm font-medium text-slate-700">Reset code</label>
                                <button
                                    type="button"
                                    id="paste-code-btn"
                                    class="hidden flex min-h-11 items-center text-sm font-medium text-sky-700 hover:text-sky-800"
                                >
                                    Paste code
                                </button>
                            </div>
                            <div class="flex justify-between gap-2" id="code-boxes">
                                @for ($i = 0; $i < 6; $i++)
                                    <input
                                        type="text"
                                        inputmode="text"
                                        pattern="[23456789ABCDEFGHJKMNPQRSTUVWXYZ]"
                                        autocomplete="one-time-code"
                                        required
                                         maxlength="1"
                                        aria-label="Code character {{ $i + 1 }}"
                                        class="code-box h-11 w-full rounded-lg border border-slate-300 text-center text-lg font-semibold uppercase focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600"
                                    >
                                @endfor
                            </div>
                            <input type="hidden" name="code" id="code" value="{{ old('code') }}">
                            @error('code')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">
                                New password
                            </label>
                            <div class="relative">
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="At least 8 characters"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 pr-10 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('password') border-red-500! @enderror"
                                >
                                <button
                                    type="button"
                                    data-password-toggle="password"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                    class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded text-slate-500 transition-colors hover:text-slate-700 focus:outline-none"
                                >
                                    <svg class="icon-eye h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="icon-eye-off hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">
                                Confirm new password
                            </label>
                            <div class="relative">
                                <input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 pr-10 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600"
                                >
                                <button
                                    type="button"
                                    data-password-toggle="password_confirmation"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                    class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded text-slate-500 transition-colors hover:text-slate-700 focus:outline-none"
                                >
                                    <svg class="icon-eye h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="icon-eye-off hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                                </button>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-full"
                        >
                            Reset Password
                        </button>
                    </form>

                    <div class="mt-4 border-t border-slate-200 pt-3 text-center text-sm text-slate-500">
                        Didn't get the code?
                        @if ($maskedEmail !== '')
                            <form action="{{ route('password.email') }}" method="POST" class="mt-2">
                                @csrf
                                <input type="hidden" name="email" value="{{ $email }}">
                                <button
                                    type="submit"
                                    id="resend-btn"
                                    @if ($cooldownSeconds > 0) disabled aria-disabled="true" @endif
                                    class="inline-flex min-h-11 items-center font-medium {{ $cooldownSeconds > 0 ? 'text-slate-500' : 'text-sky-700 hover:text-sky-800' }}"
                                >
                                    @if ($cooldownSeconds > 0)
                                        Resend available in {{ $cooldownSeconds }}s
                                    @else
                                        Send a new one
                                    @endif
                                </button>
                            </form>
                        @else
                            <a href="{{ route('password.request') }}" class="inline-flex min-h-6 items-center font-medium text-sky-700 hover:text-sky-800">
                                Enter your email
                            </a>
                        @endif
                        &middot;
                        <a href="{{ route('login') }}" class="inline-flex min-h-6 items-center font-medium text-sky-700 hover:text-sky-800">
                            Back to sign in
                        </a>
                    </div>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-slate-500">
                &copy; {{ date('Y') }} {{ config('app.name', 'Barangay Management System') }}
            </p>
        </div>
    </main>

    @if ($cooldownSeconds > 0)
        <script>
            (function () {
                const btn = document.getElementById('resend-btn');
                let remaining = {{ $cooldownSeconds }};
                const label = 'Send a new one';

                const tick = setInterval(() => {
                    remaining--;

                    if (remaining <= 0) {
                        clearInterval(tick);
                        btn.disabled = false;
                        btn.removeAttribute('aria-disabled');
                        btn.textContent = label;
                        btn.classList.remove('text-slate-500');
                        btn.classList.add('text-sky-700', 'hover:text-sky-700');
                        return;
                    }

                    btn.textContent = `Resend available in ${remaining}s`;
                }, 1000);
            })();
        </script>
    @endif

    @include('auth.partials.password-toggle')

    @include('auth.partials.code-box-helpers')

    <script>
        (function () {
            const boxes = Array.from(document.querySelectorAll('.code-box'));
            const codeField = document.getElementById('code');

            // Restore old() input after a validation error.
            if (codeField.value) {
                codeField.value.toUpperCase().split('').forEach((ch, i) => {
                    if (boxes[i]) boxes[i].value = ch;
                });
            }

            const { syncCode, applyCode, readCodeFromClipboard } = createCodeBoxController(boxes, codeField);

            // Tab-regain focus: users copy the code from their mail client, then
            // switch back — read once per focus gain, silently.
            let autoReadArmed = true;

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') autoReadArmed = true;
            });

            window.addEventListener('focus', async () => {
                if (!autoReadArmed) return;

                autoReadArmed = false;
                const text = await readCodeFromClipboard();
                if (text) applyCode(text);
            });

            // ---------- paste button (guaranteed path — a user gesture) ----------
            const pasteBtn = document.getElementById('paste-code-btn');

            if (pasteBtn) {
                pasteBtn.addEventListener('click', async () => {
                    const text = await readCodeFromClipboard();

                    if (text && applyCode(text)) return;

                    // Nothing usable on the clipboard: focus box 1 so a manual
                    // Ctrl+V lands in the boxes' own paste handler.
                    boxes[0].focus();
                });

                if (navigator.clipboard && window.isSecureContext) {
                    pasteBtn.classList.remove('hidden');
                }
            }

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
                    // fall back to strip-and-fill for fragmented codes like "343-CVQ".
                    if (applyCode(text)) return;

                    const stripped = text.replace(/[^23456789ABCDEFGHJKMNPQRSTUVWXYZ]/gi, '').toUpperCase().slice(0, boxes.length);

                    boxes.forEach((target, i) => {
                        target.value = stripped[i] ?? '';
                    });

                    boxes[Math.min(stripped.length, boxes.length - 1)].focus();
                    syncCode();
                });
            });

            document.getElementById('reset-form').addEventListener('submit', syncCode);
        })();
    </script>
</body>
</html>
