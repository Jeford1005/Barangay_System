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

        // Role-filtered navigation, grouped for the sidebar.
        if ($user && $user->isOfficeUser()) {
            $navItems = [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard', 'group' => 'Workspace'],
                ['label' => 'Residents', 'route' => 'residents.index', 'pattern' => 'residents.*', 'permission' => 'residents.view', 'group' => 'Workspace'],
                ['label' => 'Households', 'route' => 'households.index', 'pattern' => 'households.*', 'permission' => 'households.view', 'group' => 'Workspace'],
                ['label' => 'Puroks', 'route' => 'puroks.index', 'pattern' => 'puroks.*', 'permission' => 'puroks.view', 'group' => 'Workspace'],
                ['label' => 'Blotter', 'route' => 'blotter.index', 'pattern' => 'blotter.*', 'permission' => 'blotter.view', 'group' => 'Workspace'],
                ['label' => 'Welfare', 'route' => 'welfare.index', 'pattern' => 'welfare.*', 'permission' => 'welfare.view', 'group' => 'Workspace'],
                ['label' => 'Certificates', 'route' => 'certificates.index', 'pattern' => 'certificates.*', 'permission' => 'certificates.view', 'group' => 'Workspace'],
                ['label' => 'Requests', 'route' => 'admin.certificate-requests.index', 'pattern' => 'admin.certificate-requests.*', 'permission' => 'certificate-requests.view', 'group' => 'Processing', 'badge' => 'requests'],
                ['label' => 'Reports', 'route' => 'reports.index', 'pattern' => 'reports.*', 'permission' => 'reports.view', 'group' => 'Processing'],
                ['label' => 'Analytics', 'route' => 'analytics', 'pattern' => 'analytics', 'permission' => 'analytics.view', 'group' => 'Processing'],
                ['label' => 'Accounts', 'route' => 'admin.accounts.index', 'pattern' => 'admin.accounts.*', 'group' => 'Administration', 'admin' => true, 'badge' => 'accounts'],
                ['label' => 'Certificate types', 'route' => 'admin.certificate-types.index', 'pattern' => 'admin.certificate-types.*', 'group' => 'Administration', 'admin' => true],
                ['label' => 'Officials', 'route' => 'admin.officials.index', 'pattern' => 'admin.officials.*', 'group' => 'Administration', 'admin' => true],
                ['label' => 'Audit log', 'route' => 'admin.audit-logs.index', 'pattern' => 'admin.audit-logs.*', 'group' => 'Administration', 'admin' => true],
                ['label' => 'Archive', 'route' => 'archive.index', 'pattern' => 'archive.*', 'group' => 'Administration', 'admin' => true],
            ];

            $visibleNav = collect($navItems)->filter(function (array $item) use ($user) {
                if (! empty($item['admin'])) {
                    return $user->isAdmin();
                }

                return empty($item['permission']) || $user->hasPermission($item['permission']);
            });
        } elseif ($user) {
            $visibleNav = collect([
                ['label' => 'My barangay', 'route' => 'resident.portal', 'pattern' => 'resident.portal', 'group' => 'Portal'],
                ['label' => 'My requests', 'route' => 'resident.requests', 'pattern' => 'resident.requests', 'group' => 'Portal'],
            ]);
        } else {
            $visibleNav = collect();
        }

        $groupedNav = $visibleNav->groupBy('group');
    @endphp

    {{-- Mobile backdrop for the slide-in sidebar --}}
    <div data-sidebar-backdrop data-open="false"
         class="app-backdrop fixed inset-0 z-30 bg-[#08234A]/60 print:hidden"></div>

    <aside id="sidebar" data-sidebar data-open="false" aria-label="Sidebar"
           class="app-sidebar fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-[#08234A] print:hidden">
        <div class="flex items-center gap-3 border-b border-white/10 px-4 py-4">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 flex-1 items-center gap-3">
                <img src="{{ asset('images/bidduang-seal.png') }}"
                     alt="Official seal of Barangay Bidduang"
                     class="h-10 w-10 shrink-0 rounded-full ring-1 ring-white/25"
                     width="40" height="40">
                <div class="min-w-0 leading-tight">
                    <p class="truncate text-sm font-semibold text-white">Barangay Bidduang</p>
                    <p class="text-[10px] font-medium uppercase tracking-[0.16em] text-[#5CB2DE]">Management System</p>
                </div>
            </a>
            <button type="button" data-sidebar-close aria-label="Close navigation"
                    class="rounded-md p-1.5 text-white/50 transition hover:bg-white/10 hover:text-white lg:hidden">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <nav class="app-sidebar-nav flex-1 overflow-y-auto px-3 pb-4" aria-label="Main">
            @foreach ($groupedNav as $groupName => $items)
                <p class="px-3 pb-1.5 pt-5 text-[10px] font-semibold uppercase tracking-[0.18em] text-[#5CB2DE]/90">
                    {{ $groupName }}
                </p>
                <ul class="space-y-0.5">
                    @foreach ($items as $item)
                        @php
                            $badgeCount = match ($item['badge'] ?? null) {
                                'accounts' => \App\Models\User::where('status', \App\Models\User::STATUS_PENDING)->count(),
                                'requests' => \App\Models\CertificateRequest::where('status', 'Pending')->count(),
                                default => 0,
                            };
                        @endphp
                        <li>
                            <a href="{{ route($item['route']) }}"
                               @class([
                                   'flex items-center rounded-lg px-3 py-2 text-sm transition',
                                   'bg-[#5CB2DE] font-semibold text-[#08234A]' => request()->routeIs($item['pattern']),
                                   'font-medium text-white/65 hover:bg-white/[0.07] hover:text-white' => ! request()->routeIs($item['pattern']),
                               ])>
                                {{ $item['label'] }}
                                @if ($badgeCount > 0)
                                    <span class="ml-auto rounded-full bg-[#E8B93B] px-1.5 py-0.5 text-[10px] font-bold leading-none text-[#08234A]">
                                        {{ $badgeCount > 99 ? '99+' : $badgeCount }}
                                    </span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        @if ($user)
            <div class="border-t border-white/10 px-4 py-4">
                <div class="mb-3 flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#5CB2DE]/15 text-sm font-semibold text-[#5CB2DE] ring-1 ring-[#5CB2DE]/40">
                        {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-sm font-medium text-white">{{ $user->name }}</p>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#5CB2DE]">{{ $user->role }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full rounded-lg border border-white/15 px-3 py-2 text-sm font-medium text-white/80 transition hover:border-[#5CB2DE]/60 hover:bg-white/10 hover:text-white">
                        Log out
                    </button>
                </form>
            </div>
        @endif
    </aside>

    <div class="app-shell min-h-screen">
        <header class="app-topbar sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur print:hidden lg:hidden">
            <div class="flex min-h-14 items-center gap-3 px-4">
                <button type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation"
                        class="rounded-lg border border-slate-200 p-2 text-slate-600 transition hover:bg-slate-50">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M3 6h14M3 10h14M3 14h14" stroke-linecap="round" />
                    </svg>
                </button>

                <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                    <img src="{{ asset('images/bidduang-seal.png') }}" alt="" class="h-7 w-7 rounded-full ring-1 ring-slate-200" width="28" height="28">
                    <span class="text-sm font-semibold text-slate-900">Barangay Bidduang</span>
                </a>

                @if ($user)
                    <span class="ml-auto text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ $user->role }}</span>
                @endif
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-5 py-8 sm:px-6 lg:py-10">
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
    </div>
</body>
</html>
