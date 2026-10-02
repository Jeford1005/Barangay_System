<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Two-Factor Challenge - {{ config('app.name', 'Barangay Management System') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/bidduang-mark.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0f172a">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
@include('components.bare-url')
</head>
<body class="min-h-dvh bg-slate-200 text-slate-800 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-sky-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        Skip to verification form
    </a>

    {{-- Same whisper seal backdrop as the sign-in screen, so the second login
         step reads as part of the one system rather than a new page. --}}
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 z-0 flex items-center justify-center overflow-hidden">
        <img src="{{ asset('images/bidduang-seal-circle.png') }}" alt=""
            class="h-auto w-[min(150vmin,1150px)] max-w-none select-none opacity-[0.11]">
    </div>

    <main id="main-content" class="relative z-10 flex min-h-dvh items-center justify-center px-4 py-3 sm:px-6">
        <div class="w-full max-w-md">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5">

                <header class="text-center">
                    <img src="{{ asset('images/bidduang-seal-circle.png') }}" alt=""
                        class="mx-auto h-11 w-11 rounded-full">
                    <p class="mt-2 text-[13px] font-medium text-slate-500">
                        {{ config('app.name', 'Barangay Management System') }}
                    </p>
                    <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-900">Check your authenticator</h1>
                    <p class="mt-1 text-sm text-slate-500">Your password was accepted. Enter the 6-digit code from your authenticator app — or one unused recovery code.</p>
                </header>

                <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-4">
                    @csrf

                    <div>
                        <label for="code" class="mb-1.5 block text-sm font-medium text-slate-700">Verification code</label>
                        <input
                            id="code"
                            type="text"
                            name="code"
                            value="{{ old('code') }}"
                            required
                            autofocus
                            autocomplete="one-time-code"
                            inputmode="text"
                            maxlength="12"
                            placeholder="6-digit code or recovery code"
                            class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('code') border-red-500! ring-1 ring-red-500/40 @enderror"
                            @if ($errors->has('code')) aria-invalid="true" aria-describedby="two-factor-code-error" @endif
                        >
                        @error('code')
                            <p id="two-factor-code-error" role="alert" class="mt-1.5 text-[13px] text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="mt-3 btn btn-primary w-full">
                        Verify and sign in
                    </button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
