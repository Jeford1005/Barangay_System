@php
    // Which tab comes back selected. The office tab covers both office roles:
    // the account's own stored role still decides what it can reach, so this
    // only says which side of the door the visitor is knocking on.
    $selectedRole = old('user_type', 'office');
    if (! in_array($selectedRole, ['office', 'resident'], true)) {
        $selectedRole = 'office';
    }

    // The tabs carry the label; the line beneath states what the choice leads
    // to, so the page never has to say the same thing twice. Kept to a short
    // line each: a paragraph here would break the form into separate blocks.
    $roles = [
        'office' => [
            'name' => 'Admin/Staff',
            'can' => 'Records and intake. Administrators also manage accounts and settings.',
        ],
        'resident' => [
            'name' => 'Resident',
            'can' => 'Your profile, certificate requests and correction requests.',
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In - {{ config('app.name', 'Barangay Management System') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&family=poppins:500,600,700&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/bidduang-mark.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <noscript><style>.js-only { display: none !important; }</style></noscript>
</head>
<body class="min-h-screen bg-[#E8EAE5] text-emerald-950 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-full focus:bg-emerald-800 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        Skip to sign-in form
    </a>

    {{-- Full-bleed on a phone, as the reference stacks; centred from md up,
         where the split needs the page around it. --}}
    <main id="main-content" class="flex min-h-screen items-start justify-center md:items-center md:p-4 lg:p-6">
        <div class="grid w-full grid-cols-1 overflow-hidden bg-white shadow-2xl shadow-emerald-950/10 md:max-w-4xl md:grid-cols-2 lg:max-w-5xl lg:rounded-[28px]">

            {{-- ------------------------------------------------------------ --
                 Green panel. Carries the brand and the welcome only. Large,
                 crisp organic curves: the composition needs weight, so these
                 are shape, not haze. They scale with the panel and are pushed
                 into the corners so no text ever lands on the white.
                 ------------------------------------------------------------ --}}
            <section class="relative flex flex-col overflow-hidden bg-emerald-800 text-white">
                <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                    <div class="absolute -top-20 -right-14 h-48 w-48 rounded-full bg-emerald-600/30 md:-top-28 md:-right-20 md:h-72 md:w-72 lg:-top-28 lg:-right-20 lg:h-80 lg:w-80"></div>
                    <div class="absolute -bottom-28 -left-20 h-44 w-44 rounded-full bg-white sm:-bottom-32 sm:-left-24 sm:h-56 sm:w-56 md:-bottom-32 md:-left-28 md:h-64 md:w-64 lg:-bottom-40 lg:-left-28 lg:h-72 lg:w-72"></div>
                </div>

                <div class="relative flex flex-1 flex-col p-7 pb-24 sm:p-9 sm:pb-24 md:p-8 md:pb-10 lg:p-12">
                    {{-- Wordmark --}}
                    <div class="flex items-center gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white p-1.5">
                            <img src="{{ asset('images/bidduang-seal-circle.png') }}" alt="" class="h-full w-full object-contain">
                        </span>
                        <span class="font-display text-[15px] font-semibold leading-tight tracking-tight">
                            {{ config('app.name', 'Barangay Management System') }}
                        </span>
                    </div>

                    {{-- Sits between the wordmark and the sweep: the auto margins
                         centre it in whatever space the panel has, so the
                         composition holds at every height without a footer to
                         anchor the bottom. --}}
                    <div class="mt-auto mb-auto max-w-sm">
                        <h2 class="font-display text-[32px] font-bold leading-[1.15] tracking-tight sm:text-4xl">
                            Welcome Back!
                        </h2>
                        <p class="mt-3 hidden text-sm leading-relaxed text-emerald-50/80 md:block">
                            Sign in to keep every resident record, household roster, and assistance the office holds in order.
                        </p>
                    </div>
                </div>
            </section>

            {{-- ------------------------------------------------------------ --
                 Form panel. Vertical rhythm here is deliberately tight: a short
                 laptop window must still show the whole card with air around it
                 rather than a sliver of scroll.
                 ------------------------------------------------------------ --}}
            <section class="flex items-center bg-white p-7 sm:p-9 md:p-8 lg:p-10">
                <div class="w-full">
                    <h1 class="font-display text-[28px] font-bold lowercase leading-none tracking-tight text-emerald-700">
                        welcome
                    </h1>
                    <p class="mt-2 text-sm text-neutral-500">Log in to your account to continue.</p>

                    @if (session('status'))
                        <div class="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900 ring-1 ring-emerald-200" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" id="login-form" class="mt-6">
                        @csrf

                        {{-- Role tabs. An underline switch rather than pills: two
                             pills of very different widths read as two separate
                             buttons, while tabs on a shared rule read as one
                             choice between two options. The rule is neutral, so
                             only the active tab carries colour. --}}
                        <div role="tablist" aria-label="Signing in as" data-role-tabs
                            class="flex items-center gap-6 border-b border-neutral-200">
                            @foreach ($roles as $value => $role)
                                <button type="button" role="tab" id="role-tab-{{ $value }}"
                                    data-role-tab="{{ $value }}" data-role-can="{{ $role['can'] }}"
                                    aria-selected="{{ $selectedRole === $value ? 'true' : 'false' }}"
                                    tabindex="{{ $selectedRole === $value ? '0' : '-1' }}"
                                    class="js-only -mb-px border-b-2 border-transparent pb-2.5 text-[13px] font-semibold text-neutral-500 transition-colors hover:text-emerald-800 aria-selected:border-emerald-700 aria-selected:text-emerald-800 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                                    {{ $role['name'] }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Without scripting the tabs are inert, so fall back to
                             a plain select. The hidden field carries no `name`
                             until the script runs, so exactly one control ever
                             submits `user_type`. --}}
                        <noscript>
                            <label for="login-user-type-noscript" class="sr-only">Signing in as</label>
                            <select id="login-user-type-noscript" name="user_type"
                                class="h-11 w-full rounded-full border-0 bg-emerald-50 px-4 text-sm text-emerald-950 focus:outline-none focus:ring-2 focus:ring-emerald-700">
                                @foreach ($roles as $value => $role)
                                    <option value="{{ $value }}" @selected($selectedRole === $value)>{{ $role['name'] }}</option>
                                @endforeach
                            </select>
                        </noscript>

                        <input type="hidden" id="login-user-type" value="{{ $selectedRole }}">

                        <p data-role-summary class="mt-2.5 min-h-5 text-[12.5px] leading-snug text-neutral-500">
                            {{ $roles[$selectedRole]['can'] }}
                        </p>

                        <div class="mt-4 space-y-2">
                            <div>
                                <label for="email" class="sr-only">Email address</label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    spellcheck="false"
                                    placeholder="Email address"
                                    class="h-12 w-full rounded-full border-0 bg-emerald-50 px-5 text-sm text-emerald-950 placeholder:text-emerald-800/40 focus:outline-none focus:ring-2 focus:ring-emerald-700 @error('email') ring-2 ring-red-500 @enderror"
                                >
                                @error('email')
                                    <p class="mt-1.5 px-5 text-[13px] text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password" class="sr-only">Password</label>
                                <div class="relative">
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="Password"
                                        class="h-12 w-full rounded-full border-0 bg-emerald-50 pr-12 pl-5 text-sm text-emerald-950 placeholder:text-emerald-800/40 focus:outline-none focus:ring-2 focus:ring-emerald-700 @if ($errors->has('password')) ring-2 ring-red-500 @endif"
                                         @if ($errors->has('password')) aria-invalid="true" aria-describedby="login-password-error" @endif
                                    >
                                    <button
                                        type="button"
                                        data-password-toggle="password"
                                        aria-label="Show password"
                                        aria-pressed="false"
                                        class="absolute inset-y-0 right-1.5 flex w-10 items-center justify-center rounded-full text-emerald-800/60 transition-colors hover:text-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700"
                                    >
                                        <svg class="icon-eye h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0.696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                        <svg class="icon-eye-off hidden h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0.696"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                                    </button>
                                </div>
                                @error('password')
                                    <p id="login-password-error" role="alert" class="mt-1.5 flex items-start gap-1.5 px-5 text-[13px] text-red-600">
                                        <x-icon name="x-circle" class="mt-px h-4 w-4 shrink-0" />
                                        <span>{{ $message }}</span>
                                    </p>
                                @enderror
                            </div>
                        </div>

                        {{-- Tertiary action: colour carries the affordance, so no
                             rule is drawn under the text. --}}
                        <div class="mt-3 flex justify-end">
                            @if (Route::has('password.request'))
                                <button type="button" data-open-forgot class="js-only text-[13px] font-medium text-red-600 no-underline transition-colors hover:text-red-700 hover:no-underline">
                                    Forgot password
                                </button>
                                <noscript>
                                    <a href="{{ route('password.request') }}" class="text-[13px] font-medium text-red-600 no-underline hover:text-red-700 hover:no-underline">Forgot password</a>
                                </noscript>
                            @endif
                        </div>

                        <button type="submit" class="mt-5 flex h-12 w-full items-center justify-center rounded-full bg-emerald-700 text-sm font-semibold uppercase tracking-wider text-white transition-colors hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">
                            Log in
                        </button>
                    </form>

                    <p class="mt-5 text-center text-[13px] text-neutral-500">
                        <span data-role-copy="resident" @if ($selectedRole !== 'resident') class="hidden" @endif>
                            Don't have an account?
                            <button type="button" data-open-register class="js-only font-semibold text-emerald-700 underline underline-offset-2 hover:text-emerald-800">
                                Sign up
                            </button>
                            <noscript><a href="{{ route('register') }}" class="font-semibold text-emerald-700 underline underline-offset-2">Sign up</a></noscript>
                        </span>
                        <span data-role-copy="office" @if ($selectedRole === 'resident') class="hidden" @endif>
                            Residents: sign up with the office to reach your portal.
                        </span>
                    </p>
                </div>
            </section>
        </div>
    </main>

    @include('auth.partials.forgot-dialog')
    @include('auth.partials.register-dialog')

    @include('auth.partials.code-box-helpers')
    @include('auth.partials.scripts.role-switch')
    @include('auth.partials.scripts.forgot-dialog')
    @include('auth.partials.scripts.register-dialog')
    @include('auth.partials.password-toggle')
    @include('auth.partials.scripts.login-error-clear')
</body>
</html>
