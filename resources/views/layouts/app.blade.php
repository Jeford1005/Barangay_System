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
    @php
        $user = auth()->user();
    @endphp

    <header class="border-b border-slate-200 bg-white print:hidden">
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

                {{-- role-filtered navigation --}}
                <nav class="ml-4 hidden items-center gap-0.5 flex-wrap lg:flex" aria-label="Main">
                    @if ($user && $user->isOfficeUser())
                        @php
                            $navItems = [
                                ['label' => 'Dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard'],
                                ['label' => 'Residents', 'route' => 'residents.index', 'pattern' => 'residents.*', 'permission' => 'residents.view'],
                                ['label' => 'Households', 'route' => 'households.index', 'pattern' => 'households.*', 'permission' => 'households.view'],
                                ['label' => 'Puroks', 'route' => 'puroks.index', 'pattern' => 'puroks.*', 'permission' => 'puroks.view'],
                                ['label' => 'Blotter', 'route' => 'blotter.index', 'pattern' => 'blotter.*', 'permission' => 'blotter.view'],
                                ['label' => 'Welfare', 'route' => 'welfare.index', 'pattern' => 'welfare.*', 'permission' => 'welfare.view'],
                                ['label' => 'Certificates', 'route' => 'certificates.index', 'pattern' => 'certificates.*', 'permission' => 'certificates.view'],
                                ['label' => 'Requests', 'route' => 'admin.certificate-requests.index', 'pattern' => 'admin.certificate-requests.*', 'permission' => 'certificate-requests.view', 'badge' => 'requests'],
                                ['label' => 'Reports', 'route' => 'reports.index', 'pattern' => 'reports.*', 'permission' => 'reports.view'],
                                ['label' => 'Analytics', 'route' => 'analytics', 'pattern' => 'analytics', 'permission' => 'analytics.view'],
                                ['label' => 'Accounts', 'route' => 'admin.accounts.index', 'pattern' => 'admin.accounts.*', 'admin' => true, 'badge' => 'accounts'],
                                ['label' => 'Types', 'route' => 'admin.certificate-types.index', 'pattern' => 'admin.certificate-types.*', 'admin' => true],
                                ['label' => 'Officials', 'route' => 'admin.officials.index', 'pattern' => 'admin.officials.*', 'admin' => true],
                                ['label' => 'Audit', 'route' => 'admin.audit-logs.index', 'pattern' => 'admin.audit-logs.*', 'admin' => true],
                                ['label' => 'Archive', 'route' => 'archive.index', 'pattern' => 'archive.*', 'admin' => true],
                            ];

                            $visibleNav = collect($navItems)->filter(function (array $item) use ($user) {
                                if (! empty($item['admin'])) {
                                    return $user->isAdmin();
                                }

                                return empty($item['permission']) || $user->hasPermission($item['permission']);
                            });
                        @endphp

                        @foreach ($visibleNav as $item)
                            @php
                                $badgeCount = match ($item['badge'] ?? null) {
                                    'accounts' => \App\Models\User::where('status', \App\Models\User::STATUS_PENDING)->count(),
                                    'requests' => \App\Models\CertificateRequest::where('status', 'Pending')->count(),
                                    default => 0,
                                };
                            @endphp
                            <a href="{{ route($item['route']) }}"
                               @class([
                                   'relative rounded-md px-2.5 py-1.5 text-sm font-medium transition',
                                   'bg-slate-100 text-slate-900' => request()->routeIs($item['pattern']),
                                   'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs($item['pattern']),
                               ])>
                                {{ $item['label'] }}
                                @if ($badgeCount > 0)
                                    <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-bold text-white">{{ $badgeCount > 99 ? '99+' : $badgeCount }}</span>
                                @endif
                            </a>
                        @endforeach
                    @elseif ($user)
                        {{-- resident portal navigation --}}
                        <a href="{{ route('resident.portal') }}"
                           @class([
                               'rounded-md px-3 py-1.5 text-sm font-medium transition',
                               'bg-slate-100 text-slate-900' => request()->routeIs('resident.portal'),
                               'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('resident.portal'),
                           ])>
                            My barangay
                        </a>
                        <a href="{{ route('resident.requests') }}"
                           @class([
                               'rounded-md px-3 py-1.5 text-sm font-medium transition',
                               'bg-slate-100 text-slate-900' => request()->routeIs('resident.requests'),
                               'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('resident.requests'),
                           ])>
                            My requests
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
