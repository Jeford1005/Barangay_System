<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Choose a new password') }} &middot; Barangay Bidduang</title>
    <link rel="icon" type="image/png" href="{{ asset('images/bidduang-seal.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-[#08234A] text-white antialiased">
    {{-- Cagayan provincial flag: sky blue · gold · green --}}
    <div class="absolute inset-x-0 top-0 z-20 flex h-[3px]" aria-hidden="true">
        <span class="w-1/3 bg-[#5CB2DE]"></span>
        <span class="w-1/3 bg-[#E8B93B]"></span>
        <span class="w-1/3 bg-[#2F8F5B]"></span>
    </div>

    <div class="relative flex min-h-dvh flex-col overflow-hidden lg:h-dvh">
        <div class="pointer-events-none absolute -left-64 -top-72 h-[40rem] w-[40rem] rounded-full bg-[#12406F] opacity-60 blur-[130px]"></div>

        <img src="{{ asset('images/bidduang-seal.png') }}"
             alt=""
             aria-hidden="true"
             class="pointer-events-none absolute -right-28 top-1/2 hidden w-[36rem] -translate-y-1/2 select-none opacity-[0.08] lg:block xl:-right-16">

        <div class="grain-overlay pointer-events-none absolute inset-0 opacity-[0.06] mix-blend-overlay"></div>

        <div class="relative z-10 mx-auto flex w-full max-w-[86rem] min-h-0 flex-1 flex-col px-6 py-6 lg:px-14">
            <header class="flex shrink-0 items-center gap-3.5">
                <img src="{{ asset('images/bidduang-seal.png') }}"
                     alt="Official seal of Barangay Bidduang"
                     class="h-12 w-12 rounded-full ring-1 ring-white/25 ring-offset-2 ring-offset-[#08234A]"
                     width="48" height="48">
                <div class="leading-tight">
                    <p class="font-display text-lg text-white">Barangay Bidduang</p>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.3em] text-[#5CB2DE]/85">
                        Municipality of Pamplona &middot; Cagayan
                    </p>
                </div>
            </header>

            <main class="flex min-h-0 flex-1 flex-col">
                <form method="POST" action="{{ route('password.update') }}" data-submit-loading
                      data-loading-label="Resetting…"
                      class="mx-auto mt-auto mb-12 w-full max-w-[24rem] space-y-5 lg:mb-20">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    @if (session('status'))
                        <div class="rounded-lg border border-[#2F8F5B]/45 bg-[#2F8F5B]/12 px-4 py-3 text-sm text-emerald-100" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div>
                        <label for="email" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Email address</label>
                        <input id="email"
                               type="email"
                               name="email"
                               value="{{ old('email', $email) }}"
                               required
                               readonly
                               class="mt-2 block w-full cursor-not-allowed rounded-lg border border-white/10 bg-white/[0.04] px-4 py-3 text-white/70 outline-none">
                        @error('email')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">New password</label>

                        <div class="relative mt-2">
                            <input id="password"
                                   type="password"
                                   name="password"
                                   required
                                   autocomplete="new-password"
                                   placeholder="At least 8 characters"
                                   class="block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 pr-12 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('password') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">

                            <button type="button"
                                    data-toggle-password="password"
                                    aria-label="Show password"
                                    aria-controls="password"
                                    aria-pressed="false"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 rounded-md border-0 bg-transparent p-1.5 text-white/45 outline-none transition duration-200 hover:text-white/85 focus-visible:ring-2 focus-visible:ring-[#5CB2DE]/70">
                                <svg class="password-eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="20" height="20">
                                    <path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="password-eye-slash hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="20" height="20">
                                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.6 0 8.6 3.1 9.94 7a10.1 10.1 0 0 1-1.87 3.19"/>
                                    <path d="M6.06 6.06A10.9 10.9 0 0 0 2.06 11.65a1 1 0 0 0 0 .7 10.75 10.75 0 0 0 13.1 5.52"/>
                                    <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                                    <path d="m2 2 20 20"/>
                                </svg>
                            </button>
                        </div>

                        @error('password')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Confirm new password</label>
                        <input id="password_confirmation"
                               type="password"
                               name="password_confirmation"
                               required
                               autocomplete="new-password"
                               placeholder="Repeat the new password"
                               class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('password_confirmation') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                        @error('password_confirmation')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            class="w-full rounded-lg bg-[#E8B93B] px-4 py-3.5 text-sm font-semibold tracking-wide text-[#08234A] shadow-[0_14px_30px_-14px_rgba(232,185,59,0.65)] transition duration-200 hover:bg-[#F0C755] focus:outline-none focus:ring-4 focus:ring-[#E8B93B]/30 active:translate-y-px disabled:cursor-wait disabled:opacity-70">
                        Reset password
                    </button>

                    <div class="flex items-center justify-between">
                        <a href="{{ route('login') }}" class="text-sm text-white/55 transition hover:text-white">
                            &larr; Back to sign in
                        </a>
                    </div>
                </form>
            </main>
        </div>
    </div>
</body>
</html>
