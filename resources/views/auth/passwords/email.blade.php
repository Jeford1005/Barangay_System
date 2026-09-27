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
</head>
<body class="bg-neutral-100 text-neutral-900 antialiased">
    <main class="relative min-h-screen overflow-hidden bg-gradient-to-br from-neutral-900 via-neutral-900 to-blue-950 px-4 py-10 sm:px-6">
        {{-- Ambient glows, echoing the login page's brand panel --}}
        <div class="pointer-events-none absolute -top-32 -right-32 h-96 w-96 rounded-full bg-blue-600/20 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-blue-600/10 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute top-1/3 left-1/2 h-64 w-64 -translate-x-1/2 rounded-full border border-white/5" aria-hidden="true"></div>

        <div class="relative mx-auto w-full max-w-md">
            {{-- Brand lockup --}}
            <div class="mb-8 flex items-center justify-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-white p-1 shadow-lg shadow-blue-950/50">
                    <img src="{{ asset('images/bidduang-seal-circle.png') }}" alt="Barangay Bidduang official seal" class="h-full w-full object-contain">
                </div>
                <div>
                    <p class="text-sm font-semibold tracking-tight text-white">Barangay Management System</p>
                    <p class="text-xs text-neutral-400">Office of the Barangay</p>
                </div>
            </div>

            {{-- Card --}}
            <div class="rounded-2xl border border-white/10 bg-white shadow-2xl">
                {{-- Card header: blue lock tile, like the dialog --}}
                <div class="flex items-start justify-between border-b border-neutral-100 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 shadow-sm">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-base font-semibold tracking-tight">Reset your password</h1>
                            <p class="text-xs text-neutral-400">Step 1 of 2 · your email address</p>
                        </div>
                    </div>
                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">1 / 2</span>
                </div>

                <div class="px-6 py-6">
                    <p class="text-sm text-neutral-500">
                        Enter your email and we'll send you a reset code.
                    </p>

                    @if (session('status'))
                        <div class="mt-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" class="mt-5 space-y-4">
                        @csrf

                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-neutral-700">
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
                                class="w-full rounded-lg border border-neutral-300 px-3.5 py-2.5 text-sm placeholder-neutral-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 @error('email') border-red-400 @enderror"
                            >
                            @error('email')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            id="send-code-btn"
                            @if ($cooldownSeconds > 0) disabled aria-disabled="true" @endif
                            class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-neutral-300 disabled:text-neutral-500"
                        >
                            @if ($cooldownSeconds > 0)
                                Wait {{ $cooldownSeconds }}s to resend
                            @else
                                Send Reset Code
                            @endif
                        </button>
                    </form>

                    <p class="mt-5 border-t border-neutral-100 pt-4 text-center text-sm text-neutral-500">
                        Remembered it after all?
                        <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-700">Back to sign in</a>
                    </p>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-neutral-500">
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
                        btn.classList.remove('disabled:bg-neutral-300', 'disabled:text-neutral-500', 'disabled:cursor-not-allowed');
                        return;
                    }

                    btn.textContent = `Wait ${remaining}s to resend`;
                }, 1000);
            })();
        </script>
    @endif
</body>
</html>
