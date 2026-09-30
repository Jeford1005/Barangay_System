<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forgot Password - {{ config('app.name', 'Barangay Management System') }}</title>
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
                {{-- Card header: the lock marks the flow, the seal sits behind it --}}
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-600 shadow-sm">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-base font-semibold tracking-tight text-slate-900">Reset your password</h1>
                            <p class="text-xs text-slate-500">Step 1 of 2 · your email address</p>
                        </div>
                    </div>
                    <span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700">1 / 2</span>
                </div>

                <div class="px-6 py-6">
                    <p class="text-sm text-slate-500">
                        Enter your email and we'll send you a reset code.
                    </p>

                    @if (session('status'))
                        <div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" class="mt-5 space-y-4">
                        @csrf

                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">
                                Email address
                            </label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="you@example.com"
                                class="min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('email') border-red-500! @enderror"
                            >
                            @error('email')
                                <p id="email-error" class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            id="send-code-btn"
                            @if ($cooldownSeconds > 0) disabled aria-disabled="true" @endif
                            class="btn btn-primary w-full disabled:bg-slate-300 disabled:text-slate-500"
                        >
                            @if ($cooldownSeconds > 0)
                                Wait {{ $cooldownSeconds }}s to resend
                            @else
                                Send Reset Code
                            @endif
                        </button>
                    </form>

                    <p class="mt-5 border-t border-slate-200 pt-4 text-center text-sm text-slate-500">
                        Remembered it after all?
                        <a href="{{ route('login') }}" class="font-medium text-sky-700 hover:text-sky-800">Back to sign in</a>
                    </p>
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
                const btn = document.getElementById('send-code-btn');
                let remaining = {{ $cooldownSeconds }};
                const label = 'Send Reset Code';

                const tick = setInterval(() => {
                    remaining--;

                    if (remaining <= 0) {
                        clearInterval(tick);
                        btn.disabled = false;
                        btn.removeAttribute('aria-disabled');
                        btn.textContent = label;
                        btn.classList.remove('disabled:bg-slate-300', 'disabled:text-slate-500', 'disabled:cursor-not-allowed');
                        return;
                    }

                    btn.textContent = `Wait ${remaining}s to resend`;
                }, 1000);
            })();
        </script>
    @endif

    @if ($errors->has('email'))
        <script>
            (function () {
                // Same 4.5s retirement as the login page's reset dialog: the
                // message explains what just happened, so it should not sit on
                // the card forever. The field returns to neutral with it — a red
                // border outliving the reason for it reads as a mystery.
                const node = document.getElementById('email-error');
                if (!node) return;

                const input = document.getElementById('email');
                let timer = setTimeout(retire, 4500);

                function retire() {
                    clearTimeout(timer);
                    node.remove();
                    if (input) input.classList.remove('border-red-500!');
                }

                node.addEventListener('mouseenter', () => clearTimeout(timer));
                node.addEventListener('mouseleave', () => { timer = setTimeout(retire, 4500); });
            })();
        </script>
    @endif
</body>
</html>
