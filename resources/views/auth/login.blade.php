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
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/bidduang-mark.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <noscript><style>.js-only { display: none !important; }</style></noscript>
@include('components.bare-url')
</head>
<body class="min-h-screen bg-slate-200 text-slate-800 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-sky-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        Skip to sign-in form
    </a>

    {{-- The seal, oversized and held at a whisper: enough to say whose system
         this is, never enough to argue with the form in front of it. Fixed to
         the viewport so it stays put while a short laptop window scrolls. --}}
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 z-0 flex items-center justify-center overflow-hidden">
        <img src="{{ asset('images/bidduang-seal-circle.png') }}" alt=""
            class="h-auto w-[min(150vmin,1150px)] max-w-none select-none opacity-[0.11]">
    </div>

    {{-- py-4 rather than py-10: on a tall window the card is centred and the
         padding never binds, so it costs nothing visually - but on a short
         window it is the difference between the card fitting the fold and the
         page scrolling. Kept deliberately small because every auth screen
         shares it and the sign-in card has to land under ~600px. --}}
    <main id="main-content" class="relative z-10 flex min-h-screen items-center justify-center px-4 py-3 sm:px-6">
        <div class="w-full max-w-md">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5">

                <header class="text-center">
                    <img src="{{ asset('images/bidduang-seal-circle.png') }}" alt=""
                        class="mx-auto h-11 w-11 rounded-full">
                    <p class="mt-2 text-[13px] font-medium text-slate-500">
                        {{ config('app.name', 'Barangay Management System') }}
                    </p>
                    <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-900">Sign in</h1>
                </header>

                @if (session('status'))
                    <div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" id="login-form" class="mt-4">
                    @csrf

                    {{-- Tabs on a shared rule rather than two pills: the labels
                         are very different widths, so pills would read as two
                         unrelated buttons instead of one choice. The rule stays
                         neutral so only the active tab carries colour. --}}
                    <div role="tablist" aria-label="Signing in as" data-role-tabs
                        class="flex items-center gap-6 border-b border-slate-200">
                        @foreach ($roles as $value => $role)
                            <button type="button" role="tab" id="role-tab-{{ $value }}"
                                data-role-tab="{{ $value }}" data-role-can="{{ $role['can'] }}"
                                aria-selected="{{ $selectedRole === $value ? 'true' : 'false' }}"
                                tabindex="{{ $selectedRole === $value ? '0' : '-1' }}"
                                class="js-only -mb-px border-b-2 border-transparent pb-2.5 text-[13px] font-semibold text-slate-500 transition-colors hover:text-slate-800 aria-selected:border-sky-600 aria-selected:text-slate-900 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600">
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
                            class="mt-3 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                            @foreach ($roles as $value => $role)
                                <option value="{{ $value }}" @selected($selectedRole === $value)>{{ $role['name'] }}</option>
                            @endforeach
                        </select>
                    </noscript>

                    <input type="hidden" id="login-user-type" value="{{ $selectedRole }}">

                    {{-- No role blurb here. It cost two lines of reserved height
                         (mt-3 + min-h-10 = 52px) and pushed the card past the
                         fold on short windows; the tabs and the closing line
                         below already carry the choice. role-switch guards on
                         [data-role-summary] with an `if`, so dropping the node
                         is safe. --}}
                    <div class="mt-3 space-y-3">
                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email address</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                spellcheck="false"
                                placeholder="name@example.com"
                                class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @error('email') border-red-500! ring-1 ring-red-500/40 @enderror"
                            >
                            @error('email')
                                <p class="mt-1.5 text-[13px] text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-slate-700">Password</label>

                            <div class="relative mt-1.5">
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Your password"
                                    class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 pr-11 text-sm text-slate-900 placeholder:text-slate-500 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600 @if ($errors->has('password')) border-red-500! ring-1 ring-red-500/40 @endif"
                                     @if ($errors->has('password')) aria-invalid="true" aria-describedby="login-password-error" @endif
                                >
                                <button
                                    type="button"
                                    data-password-toggle="password"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                    class="absolute inset-y-0 right-1 flex w-10 items-center justify-center rounded-md text-slate-500 transition-colors hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600"
                                >
                                    <svg class="icon-eye h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="icon-eye-off hidden h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                                </button>
                            </div>

                            @error('password')
                                <p id="login-password-error" role="alert" class="mt-1.5 flex items-start gap-1.5 text-[13px] text-red-600">
                                    <x-icon name="x-circle" class="mt-px h-4 w-4 shrink-0" />
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror

                            {{-- Recovery belongs to the input it acts on, so it
                                 sits under the field rather than beside the
                                 label. Red is the palette's critical token
                                 (#DC2626 - 4.83:1 on white, so it holds AA),
                                 which also matches the error line above it. --}}
                            @if (Route::has('password.request'))
                                <div class="mt-1.5 text-right">
                                    <button type="button" data-open-forgot class="js-only rounded text-[13px] font-medium text-red-600 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2">
                                        Forgot password?
                                    </button>
                                    <noscript>
                                        <a href="{{ route('password.request') }}" class="rounded text-[13px] font-medium text-red-600 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2">Forgot password?</a>
                                    </noscript>
                                </div>
                            @endif
                        </div>
                    </div>

                    <button type="submit" class="mt-3 flex min-h-11 w-full items-center justify-center rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-sky-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 focus-visible:ring-offset-2">
                        Sign in
                    </button>
                </form>

                <p class="mt-2 border-t border-slate-200 pt-2.5 text-center text-[13px] text-slate-500">
                    <span data-role-copy="resident" @if ($selectedRole !== 'resident') class="hidden" @endif>
                        Don't have an account?
                        <button type="button" data-open-register class="js-only font-semibold text-sky-700 underline underline-offset-2 hover:text-sky-800">
                            Sign up
                        </button>
                        <noscript><a href="{{ route('register') }}" class="font-semibold text-sky-700 underline underline-offset-2">Sign up</a></noscript>
                    </span>
                    <span data-role-copy="office" @if ($selectedRole === 'resident') class="hidden" @endif>
                        Residents: sign up with the office to reach your portal.
                    </span>
                </p>
            </div>
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
