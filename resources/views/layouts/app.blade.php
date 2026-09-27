<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name')) &middot; Barangay Bidduang</title>
    <link rel="icon" type="image/png" href="{{ asset('images/bidduang-seal.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 antialiased">
    @php($user = auth()->user())

    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex min-h-16 max-w-7xl flex-wrap items-center justify-between gap-3 px-6 py-2">
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <img src="{{ asset('images/bidduang-seal.png') }}"
                         alt="Official seal of Barangay Bidduang"
                         class="h-9 w-9 rounded-full ring-1 ring-slate-200"
                         width="36" height="36">
                    <div class="leading-tight">
                        <p class="text-sm font-semibold text-slate-900">Barangay Bidduang</p>
                        <p class="text-[10px] uppercase tracking-[0.16em] text-slate-400">Barangay Management System</p>
                    </div>
                </a>

                {{-- role-based navigation --}}
                <nav class="ml-4 hidden items-center gap-1 sm:flex" aria-label="Main">
                    <a href="{{ route('dashboard') }}"
                       @class([
                           'rounded-md px-3 py-1.5 text-sm font-medium transition',
                           'bg-slate-100 text-slate-900' => request()->routeIs('dashboard'),
                           'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('dashboard'),
                       ])>
                        Dashboard
                    </a>

                    @if ($user?->isAdmin())
                        <a href="{{ route('admin.accounts.index') }}"
                           @class([
                               'rounded-md px-3 py-1.5 text-sm font-medium transition',
                               'bg-slate-100 text-slate-900' => request()->routeIs('admin.*'),
                               'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('admin.*'),
                           ])>
                            Accounts
                        </a>
                    @endif
                </nav>
            </div>

            <div class="flex items-center gap-4">
                @if ($user)
                    <span class="hidden text-right leading-tight sm:block">
                        <span class="block text-sm font-medium text-slate-700">{{ $user->name }}</span>
                        <span class="block text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $user->role }}</span>
                    </span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Log out
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-6 py-10">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
