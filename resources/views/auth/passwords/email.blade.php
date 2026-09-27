<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Reset password') }} &middot; Barangay Bidduang</title>
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
                <form method="POST" action="{{ route('password.email') }}" data-submit-loading
                      data-loading-label="Sending…"
                      class="mx-auto mt-auto mb-12 w-full max-w-[24rem] space-y-5 lg:mb-20">
                    @csrf

                    @if (session('status'))
                        <div class="rounded-lg border border-[#2F8F5B]/45 bg-[#2F8F5B]/12 px-4 py-3 text-sm text-emerald-100" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div>
                        <label for="reset_email" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Email address</label>
                        <input id="reset_email"
                               type="email"
                               name="reset_email"
                               value="{{ old('reset_email') }}"
                               required
                               autofocus
                               autocomplete="username"
                               placeholder="name@barangay.bidduang"
                               class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('reset_email') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                        @error('reset_email')
                            <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            class="w-full rounded-lg bg-[#E8B93B] px-4 py-3.5 text-sm font-semibold tracking-wide text-[#08234A] shadow-[0_14px_30px_-14px_rgba(232,185,59,0.65)] transition duration-200 hover:bg-[#F0C755] focus:outline-none focus:ring-4 focus:ring-[#E8B93B]/30 active:translate-y-px disabled:cursor-wait disabled:opacity-70">
                        Send reset link
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
