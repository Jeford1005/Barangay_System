<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('Sign in') }} &middot; Barangay Bidduang</title>
    <link rel="icon" type="image/png" href="{{ asset('images/bidduang-seal.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-[#08234A] text-white antialiased">
    <a href="#signin-form"
       class="sr-only rounded bg-[#E8B93B] px-4 py-2 text-sm font-semibold text-[#08234A] focus:not-sr-only focus:absolute focus:left-6 focus:top-6 focus:z-50">
        Skip to sign-in form
    </a>

    {{-- Cagayan provincial flag: sky blue · gold · green --}}
    <div class="absolute inset-x-0 top-0 z-20 flex h-[3px]" aria-hidden="true">
        <span class="w-1/3 bg-[#5CB2DE]"></span>
        <span class="w-1/3 bg-[#E8B93B]"></span>
        <span class="w-1/3 bg-[#2F8F5B]"></span>
    </div>

    {{-- One screen, no scrolling: header top, form centered, nothing else. --}}
    <div class="relative flex min-h-dvh flex-col overflow-hidden lg:h-dvh">

        {{-- light enters from the upper left, once --}}
        <div class="pointer-events-none absolute -left-64 -top-72 h-[40rem] w-[40rem] rounded-full bg-[#12406F] opacity-60 blur-[130px]"></div>

        {{-- seal watermark, bleeding off the right edge --}}
        <img src="{{ asset('images/bidduang-seal.png') }}"
             alt=""
             aria-hidden="true"
             class="pointer-events-none absolute -right-28 top-1/2 hidden w-[36rem] -translate-y-1/2 select-none opacity-[0.08] lg:block xl:-right-16">

        {{-- grain, so the navy never reads as flat vector --}}
        <div class="grain-overlay pointer-events-none absolute inset-0 opacity-[0.06] mix-blend-overlay"></div>

        <div class="relative z-10 mx-auto flex w-full max-w-[86rem] min-h-0 flex-1 flex-col px-6 py-6 lg:px-14">

            {{-- ── masthead ── --}}
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

            {{-- ── sign-in form, centered ── --}}
            <main id="main" class="flex min-h-0 flex-1 flex-col">
                <form id="signin-form" method="POST" action="{{ route('login') }}" data-submit-loading class="mx-auto mt-auto mb-12 w-full max-w-[24rem] space-y-5 lg:mb-20">
                    @csrf

                    @if (session('status') && !($openResetModal ?? false) && !($openRegisterModal ?? false))
                        <div class="rounded-lg border border-[#2F8F5B]/45 bg-[#2F8F5B]/12 px-4 py-3 text-sm text-emerald-100" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div>
                        <label for="email" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Email address</label>
                        <input id="email"
                               type="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autofocus
                               autocomplete="username"
                               placeholder="name@barangay.bidduang"
                               aria-describedby="{{ $errors->has('email') ? 'email-error' : null }}"
                               class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('email') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                        @error('email')
                            <p id="email-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Password</label>

                        <div class="relative mt-2">
                            <input id="password"
                                   type="password"
                                   name="password"
                                   required
                                   autocomplete="current-password"
                                   placeholder="Enter your password"
                                   aria-describedby="{{ $errors->has('password') ? 'password-error' : null }}"
                                   class="block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 pr-12 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('password') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">

                            {{-- show / hide password: no border, no outline on click --}}
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

                        <div class="mt-2 flex items-start justify-between gap-3">
                            @error('password')
                                <p id="password-error" class="text-sm leading-snug text-red-300">{{ $message }}</p>
                            @enderror

                            <button type="button"
                                    data-open-modal="reset-modal"
                                    class="ml-auto shrink-0 text-sm text-[#FF6B5C] underline-offset-4 transition duration-200 hover:text-[#FF9185] hover:underline">
                                Reset password
                            </button>
                        </div>
                    </div>

                    <button type="submit"
                            class="mt-1 w-full rounded-lg bg-[#E8B93B] px-4 py-3.5 text-sm font-semibold tracking-wide text-[#08234A] shadow-[0_14px_30px_-14px_rgba(232,185,59,0.65)] transition duration-200 hover:bg-[#F0C755] focus:outline-none focus:ring-4 focus:ring-[#E8B93B]/30 active:translate-y-px disabled:cursor-wait disabled:opacity-70">
                        Sign in
                    </button>

                    {{-- residents register themselves; staff/officials are made by the admin --}}
                    <p class="pt-1 text-center text-sm text-white/55">
                        New resident?
                        <button type="button"
                                data-open-modal="register-modal"
                                class="text-[#5CB2DE] underline-offset-4 transition duration-200 hover:text-[#8ECBEC] hover:underline">
                            Create an account
                        </button>
                    </p>
                </form>
            </main>
        </div>
    </div>

    {{-- ── Reset password dialog — centered on screen ── --}}
    <div id="reset-modal"
         data-modal
         @unless(empty($openResetModal)) data-open-on-load @endunless
         role="dialog"
         aria-modal="true"
         aria-labelledby="reset-modal-title"
         aria-hidden="true"
         class="fixed inset-0 z-40 hidden">

        {{-- backdrop --}}
        <div class="absolute inset-0 bg-[#041229]/75 backdrop-blur-sm" data-close-modal></div>

        {{-- centered panel --}}
        <div class="absolute inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
                <div class="relative w-full max-w-md rounded-2xl border border-white/10 bg-[#0B2C57] p-6 shadow-[0_30px_80px_-20px_rgba(2,10,25,0.9)] sm:p-7">

                    <button type="button"
                            data-close-modal
                            aria-label="Close dialog"
                            class="absolute right-4 top-4 rounded-md border-0 bg-transparent p-1.5 text-white/45 outline-none transition duration-200 hover:text-white focus-visible:ring-2 focus-visible:ring-[#5CB2DE]/70">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" width="18" height="18">
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>
                    </button>

                    <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#E8B93B]/85">Account recovery</p>
                    <h2 id="reset-modal-title" class="font-display mt-2 text-2xl leading-tight text-white">Reset password</h2>
                    <p class="mt-2 text-sm leading-relaxed text-white/55">
                        Enter the email of your barangay account and we will send you a link to set a new password.
                    </p>

                    @if (session('status') && ($openResetModal ?? false))
                        <div class="mt-5 rounded-lg border border-[#2F8F5B]/45 bg-[#2F8F5B]/12 px-4 py-3 text-sm text-emerald-100" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}"
                          data-submit-loading data-loading-label="Sending…"
                          class="mt-6 space-y-4">
                        @csrf

                        <div>
                            <label for="reset_email" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Email address</label>
                            <input id="reset_email"
                                   type="email"
                                   name="reset_email"
                                   value="{{ old('reset_email') }}"
                                   required
                                   autocomplete="username"
                                   placeholder="name@barangay.bidduang"
                                   class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('reset_email') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                            @error('reset_email')
                                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                                class="w-full rounded-lg bg-[#E8B93B] px-4 py-3 text-sm font-semibold tracking-wide text-[#08234A] shadow-[0_14px_30px_-14px_rgba(232,185,59,0.65)] transition duration-200 hover:bg-[#F0C755] focus:outline-none focus:ring-4 focus:ring-[#E8B93B]/30 active:translate-y-px disabled:cursor-wait disabled:opacity-70">
                            Send reset link
                        </button>
                    </form>

                    <div class="mt-5 border-t border-white/10 pt-4 text-center">
                        <button type="button" data-close-modal class="text-sm text-white/55 transition hover:text-white">
                            &larr; Back to sign in
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Create an account dialog — centered on screen (residents only) ── --}}
    <div id="register-modal"
         data-modal
         @unless(empty($openRegisterModal)) data-open-on-load @endunless
         role="dialog"
         aria-modal="true"
         aria-labelledby="register-modal-title"
         aria-hidden="true"
         class="fixed inset-0 z-40 hidden">

        {{-- backdrop --}}
        <div class="absolute inset-0 bg-[#041229]/75 backdrop-blur-sm" data-close-modal></div>

        {{-- centered panel --}}
        <div class="absolute inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
                <div class="relative w-full max-w-xl rounded-2xl border border-white/10 bg-[#0B2C57] p-6 shadow-[0_30px_80px_-20px_rgba(2,10,25,0.9)] sm:p-7">

                    <button type="button"
                            data-close-modal
                            aria-label="Close dialog"
                            class="absolute right-4 top-4 rounded-md border-0 bg-transparent p-1.5 text-white/45 outline-none transition duration-200 hover:text-white focus-visible:ring-2 focus-visible:ring-[#5CB2DE]/70">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" width="18" height="18">
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>
                    </button>

                    <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#E8B93B]/85">Resident portal</p>
                    <h2 id="register-modal-title" class="font-display mt-2 text-2xl leading-tight text-white">Create an account</h2>
                    <p class="mt-2 text-sm leading-relaxed text-white/55">
                        For Barangay Bidduang residents. Staff and official accounts are created by the barangay administrator.
                    </p>

                    @if (session('status') && ($openRegisterModal ?? false))
                        <div class="mt-5 rounded-lg border border-[#2F8F5B]/45 bg-[#2F8F5B]/12 px-4 py-3 text-sm text-emerald-100" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register') }}"
                          data-submit-loading data-loading-label="Creating…"
                          class="mt-6 space-y-4">
                        @csrf

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="reg_first_name" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">First name <span class="text-white/40 normal-case tracking-normal">*</span></label>
                                <input id="reg_first_name"
                                       type="text"
                                       name="first_name"
                                       value="{{ old('first_name') }}"
                                       required
                                       maxlength="80"
                                       autocomplete="given-name"
                                       placeholder="Juan"
                                       class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('first_name', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                                @error('first_name', 'register')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="reg_last_name" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Last name <span class="text-white/40 normal-case tracking-normal">*</span></label>
                                <input id="reg_last_name"
                                       type="text"
                                       name="last_name"
                                       value="{{ old('last_name') }}"
                                       required
                                       maxlength="80"
                                       autocomplete="family-name"
                                       placeholder="Dela Cruz"
                                       class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('last_name', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                                @error('last_name', 'register')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="reg_middle_name" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Middle name <span class="text-white/40 normal-case tracking-normal">optional</span></label>
                                <input id="reg_middle_name"
                                       type="text"
                                       name="middle_name"
                                       value="{{ old('middle_name') }}"
                                       maxlength="80"
                                       autocomplete="additional-name"
                                       placeholder="Santos"
                                       class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('middle_name', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                                @error('middle_name', 'register')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="reg_suffix" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Suffix <span class="text-white/40 normal-case tracking-normal">optional</span></label>
                                <input id="reg_suffix"
                                       type="text"
                                       name="suffix"
                                       value="{{ old('suffix') }}"
                                       maxlength="10"
                                       placeholder="Jr., Sr., III"
                                       class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('suffix', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                                @error('suffix', 'register')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="reg_birth_date" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Birth date <span class="text-white/40 normal-case tracking-normal">*</span></label>
                                <input id="reg_birth_date"
                                       type="date"
                                       name="birth_date"
                                       value="{{ old('birth_date') }}"
                                       required
                                       min="1900-01-01"
                                       max="{{ now()->toDateString() }}"
                                       class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white [color-scheme:dark] shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('birth_date', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                                @error('birth_date', 'register')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="reg_sex" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Sex <span class="text-white/40 normal-case tracking-normal">*</span></label>
                                <select id="reg_sex"
                                        name="sex"
                                        required
                                        class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('sex', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                                    <option value="" class="bg-[#0B2C57]" @selected(old('sex') === null)>Select…</option>
                                    <option value="Male" class="bg-[#0B2C57]" @selected(old('sex') === 'Male')>Male</option>
                                    <option value="Female" class="bg-[#0B2C57]" @selected(old('sex') === 'Female')>Female</option>
                                    <option value="Other" class="bg-[#0B2C57]" @selected(old('sex') === 'Other')>Other</option>
                                </select>
                                @error('sex', 'register')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="reg_civil_status" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Civil status <span class="text-white/40 normal-case tracking-normal">*</span></label>
                                <select id="reg_civil_status"
                                        name="civil_status"
                                        required
                                        class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('civil_status', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                                    <option value="" class="bg-[#0B2C57]" @selected(old('civil_status') === null)>Select…</option>
                                    <option value="Single" class="bg-[#0B2C57]" @selected(old('civil_status') === 'Single')>Single</option>
                                    <option value="Married" class="bg-[#0B2C57]" @selected(old('civil_status') === 'Married')>Married</option>
                                    <option value="Divorced" class="bg-[#0B2C57]" @selected(old('civil_status') === 'Divorced')>Divorced</option>
                                    <option value="Widowed" class="bg-[#0B2C57]" @selected(old('civil_status') === 'Widowed')>Widowed</option>
                                    <option value="Separated" class="bg-[#0B2C57]" @selected(old('civil_status') === 'Separated')>Separated</option>
                                </select>
                                @error('civil_status', 'register')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="reg_phone_number" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Mobile number <span class="text-white/40 normal-case tracking-normal">optional</span></label>
                                <input id="reg_phone_number"
                                       type="tel"
                                       name="phone_number"
                                       value="{{ old('phone_number') }}"
                                       maxlength="30"
                                       autocomplete="tel"
                                       placeholder="09XX XXX XXXX"
                                       class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('phone_number', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                                @error('phone_number', 'register')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="reg_address" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Home address <span class="text-white/40 normal-case tracking-normal">*</span></label>
                            <input id="reg_address"
                                   type="text"
                                   name="address"
                                   value="{{ old('address') }}"
                                   required
                                   maxlength="255"
                                   autocomplete="street-address"
                                   placeholder="House no., street, sitio"
                                   class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('address', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                            @error('address', 'register')
                                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="reg_purok_id" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Purok <span class="text-white/40 normal-case tracking-normal">optional</span></label>
                                <select id="reg_purok_id"
                                        name="purok_id"
                                        class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('purok_id', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                                    <option value="" class="bg-[#0B2C57]" @selected(old('purok_id') === null)>Select…</option>
                                    @foreach ($puroks as $purok)
                                        <option value="{{ $purok->id }}" class="bg-[#0B2C57]" @selected((string) old('purok_id') === (string) $purok->id)>
                                            {{ $purok->code }} &middot; {{ $purok->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('purok_id', 'register')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="reg_household_id" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Household <span class="text-white/40 normal-case tracking-normal">optional</span></label>
                                <select id="reg_household_id"
                                        name="household_id"
                                        class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('household_id', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                                    <option value="" class="bg-[#0B2C57]" @selected(old('household_id') === null)>Select…</option>
                                    @foreach ($households as $household)
                                        <option value="{{ $household->id }}" class="bg-[#0B2C57]" @selected((string) old('household_id') === (string) $household->id)>
                                            {{ $household->household_number }} &middot; {{ $household->address }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('household_id', 'register')
                                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="reg_email" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Email address <span class="text-white/40 normal-case tracking-normal">*</span></label>
                            <input id="reg_email"
                                   type="email"
                                   name="email"
                                   value="{{ old('email') }}"
                                   required
                                   maxlength="150"
                                   autocomplete="email"
                                   placeholder="name@example.com"
                                   class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('email', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                            @error('email', 'register')
                                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="reg_password" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Password <span class="text-white/40 normal-case tracking-normal">*</span></label>

                            <div class="relative mt-2">
                                <input id="reg_password"
                                       type="password"
                                       name="password"
                                       required
                                       minlength="8"
                                       maxlength="100"
                                       autocomplete="new-password"
                                       placeholder="At least 8 characters"
                                       class="block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 pr-12 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('password', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">

                                <button type="button"
                                        data-toggle-password="reg_password"
                                        aria-label="Show password"
                                        aria-controls="reg_password"
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

                            @error('password', 'register')
                                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="reg_password_confirmation" class="block text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5CB2DE]/90">Confirm password <span class="text-white/40 normal-case tracking-normal">*</span></label>
                            <input id="reg_password_confirmation"
                                   type="password"
                                   name="password_confirmation"
                                   required
                                   maxlength="100"
                                   autocomplete="new-password"
                                   placeholder="Re-enter your password"
                                   class="mt-2 block w-full rounded-lg border border-white/12 bg-white/[0.07] px-4 py-3 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] outline-none transition duration-200 placeholder:text-white/30 hover:bg-white/[0.09] focus:border-[#5CB2DE]/70 focus:bg-white/[0.10] focus:ring-4 focus:ring-[#5CB2DE]/20 @error('password_confirmation', 'register') border-red-400/70 focus:border-red-400/70 focus:ring-red-500/15 @enderror">
                            @error('password_confirmation', 'register')
                                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                                class="w-full rounded-lg bg-[#E8B93B] px-4 py-3 text-sm font-semibold tracking-wide text-[#08234A] shadow-[0_14px_30px_-14px_rgba(232,185,59,0.65)] transition duration-200 hover:bg-[#F0C755] focus:outline-none focus:ring-4 focus:ring-[#E8B93B]/30 active:translate-y-px disabled:cursor-wait disabled:opacity-70">
                            Create account
                        </button>
                    </form>

                    <div class="mt-5 border-t border-white/10 pt-4 text-center">
                        <button type="button" data-close-modal class="text-sm text-white/55 transition hover:text-white">
                            &larr; Back to sign in
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
