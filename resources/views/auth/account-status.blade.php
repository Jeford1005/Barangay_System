<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} &middot; Barangay Bidduang</title>
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

    <div class="relative flex min-h-dvh flex-col overflow-hidden">
        <div class="pointer-events-none absolute -left-64 -top-72 h-[40rem] w-[40rem] rounded-full bg-[#12406F] opacity-60 blur-[130px]"></div>

        <img src="{{ asset('images/bidduang-seal.png') }}"
             alt=""
             aria-hidden="true"
             class="pointer-events-none absolute -right-28 top-1/2 hidden w-[36rem] -translate-y-1/2 select-none opacity-[0.08] lg:block">

        <div class="grain-overlay pointer-events-none absolute inset-0 opacity-[0.06] mix-blend-overlay"></div>

        <div class="relative z-10 mx-auto flex w-full max-w-[86rem] flex-1 flex-col px-6 py-6 lg:px-14">
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

            <main id="main" class="flex flex-1 flex-col">
                <div class="mx-auto mt-auto mb-12 w-full max-w-[26rem] lg:mb-20">
                    <div class="rounded-2xl border border-white/10 bg-[#0B2C57] p-7 shadow-[0_30px_80px_-20px_rgba(2,10,25,0.9)]">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#E8B93B]/85">Barangay Bidduang</p>
                        <h1 class="font-display mt-2 text-2xl leading-tight text-white">{{ $title }}</h1>

                        <p class="mt-3 text-sm leading-relaxed text-white/65">{{ $message }}</p>

                        <div class="mt-6 flex items-center justify-between gap-4 border-t border-white/10 pt-5">
                            <p class="text-xs text-white/45">{{ $user->email }}</p>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="rounded-lg border border-white/15 bg-white/[0.06] px-4 py-2 text-sm font-medium text-white/80 transition duration-200 hover:bg-white/[0.12] hover:text-white focus:outline-none focus:ring-4 focus:ring-white/10">
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
