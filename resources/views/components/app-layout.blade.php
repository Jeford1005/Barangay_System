@php
    // Fragment mode exists so the CRUD dialog fetcher can inject a bare form.
    // Honouring a bare `?fragment=1` on any URL would let a crafted link strip
    // the head, sidebar, and scripts from any page, so require the same
    // XHR header the dialog script already sends.
    $isFragment = request()->boolean('fragment') && request()->ajax();

    // Embed mode renders the same authorized content without the app chrome so
    // the settings dialog can frame it (?embed=1, same-origin — X-Frame-Options
    // is SAMEORIGIN). It is cosmetic only: head, scripts, toasts, dialogs and
    // every middleware stay exactly as they are, so no gate can be widened by
    // framing a URL the visitor could not already open.
    $isEmbed = request()->boolean('embed') && ! $isFragment;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="same-origin">
    <title>{{ config('app.name', 'Barangay Management System') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600" rel="stylesheet" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/bidduang-mark.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0f172a">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    @unless ($isFragment)
        @include('components.bare-url')
    @endunless
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- No built assets: fall back to the Tailwind Play CDN so pages render without a build step. --}}
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
{{-- App shell: h-dvh + overflow-hidden locks the viewport. h-dvh (not h-screen/100vh)
     tracks Chrome's URL bar as it shows/hides, so the pinned header and the
     scroll container never jump or half-hide. The page body itself
     NEVER scrolls; only the #content-scroll region inside does. The sidebar is an
     icon rail: full (w-64) by default, minimized (w-[68px], icons only) via the
     rail toggle, persisted in localStorage. On mobile it is the off-canvas drawer. --}}
<body class="bg-slate-50 text-slate-900 antialiased {{ $isFragment ? 'bg-white' : 'h-dvh overflow-hidden' }}">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[110] focus:rounded-md focus:bg-sky-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        Skip to main content
    </a>

    <div class="flex h-full">
        @unless ($isEmbed)
        {{-- Sidebar: icon rail on desktop (minimizable), off-canvas drawer on mobile --}}
        <aside id="sidebar"
            class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full lg:translate-x-0 transition-all duration-200 ease-in-out bg-slate-900 text-white flex flex-col"
            data-minimized="false" aria-hidden="true" inert>

            {{-- Rail header: seal chip + two-line name (expanded) + collapse toggle --}}
            <div class="flex h-[96px] shrink-0 items-center gap-3 overflow-hidden border-b border-white/[0.07] px-4">
                <a href="{{ route('dashboard') }}" class="sidebar-brand" title="{{ config('app.name', 'Barangay Management System') }}">
                    <span class="sidebar-brand-mark"><img src="{{ asset('images/bidduang-seal-circle.png') }}" alt="Barangay seal"></span>
                </a>
                <button type="button" id="sidebar-minimize"
                    class="sidebar-toggle ml-auto hidden lg:inline-flex"
                    title="Minimize sidebar" aria-label="Minimize sidebar" aria-expanded="true" aria-controls="sidebar">
                    <svg class="h-4 w-4 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m11 18-6-6 6-6M18 18l-6-6 6-6"/></svg>
                </button>
                {{-- Mobile: close the drawer --}}
                <button type="button" id="sidebar-close"
                    class="lg:hidden ml-auto inline-flex items-center justify-center h-11 w-11 shrink-0 rounded-md text-slate-300 hover:bg-white/10 hover:text-white"
                    aria-label="Close navigation" onclick="toggleSidebar(false)">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto overflow-x-hidden px-3 py-2" aria-label="Main navigation">
                <div class="flex flex-col gap-0.5 pb-2">
                    @include('components.nav-items')
                </div>
            </nav>

            <div class="shrink-0 border-t border-white/[0.07] px-3 py-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Logout" aria-label="Logout" class="nav-row w-full hover:bg-red-400/[0.08] hover:text-red-200">
                        <span class="nav-icon"><svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg></span>
                        <span class="sidebar-label">{{ __('Logout') }}</span>
                    </button>
                </form>
            </div>
        </aside>

        {{-- Mobile drawer backdrop --}}
        <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-950/50 lg:hidden" onclick="toggleSidebar(false)"></div>
        @endunless

        {{-- Content column: its own height is locked to the viewport; it never scrolls. --}}
        <div id="app-content" class="flex-1 flex flex-col {{ $isEmbed ? '' : 'lg:ml-64' }} min-w-0 h-full transition-[margin] duration-200 ease-in-out">

            @unless ($isFragment)
                {{-- Scheduled-downtime notice / active-maintenance banner. The
                     component renders nothing when off, so this is a no-op for
                     normal operation; view-only, it never touches the
                     `php artisan down` secret-bypass flow. --}}
                <x-maintenance-banner />
            @endunless

            @hasSection('page_header')
                {{-- Pinned title bar: flex sibling above the scroll region — cannot scroll.
                     On mobile it leads with the hamburger (replacing the removed black header). --}}
                @yield('page_header')
            @endif

            <main id="main-content" class="flex-1 min-h-0">
                {{-- THE only scrollable region of the app. --}}
                <div id="content-scroll" class="h-full overflow-y-auto">
                    @if ($isFragment)
                        {{-- Fragment mode: CRUD dialogs fetch this page and inject only the
                             bare form card (no app chrome). The card includes its own <form>. --}}
                        <div class="p-1" data-crud-fragment>{{ $slot }}@yield('content')</div>
                    @else
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 pb-6 w-full">
                            {{ $slot }}
                            @yield('content')
                        </div>
                    @endif
                </div>
            </main>
        </div>
    </div>

    <x-toasts />

    @if (! $isFragment)
    {{-- Styled replacement for the native confirm() used by CRUD actions. --}}
    <x-confirm-dialog />

    {{-- Red message box for client-side failures (oversize file, upload, network). --}}
    <x-error-dialog />

    @unless ($isEmbed)
    {{-- Settings pop-up: ships only where its trigger does — the admin sidebar link. --}}
    @if (auth()->user()?->isAdmin())
        <x-settings-dialog />
    @endif

    <script>
        // ---- Off-canvas drawer (mobile) --------------------------------
        var sidebar = document.getElementById('sidebar');
        var sidebarBackdrop = document.getElementById('sidebar-backdrop');
        var sidebarToggle = document.getElementById('sidebar-toggle');
        var sidebarClose = document.getElementById('sidebar-close');
        var sidebarLastFocused = null;

        function setInert(element, inert) {
            if (!element) return;
            element.inert = inert;
            if (inert) element.setAttribute('inert', '');
            else element.removeAttribute('inert');
        }

        function isMobileSidebar() {
            return window.innerWidth < 1024;
        }

        function setSidebarState(open, restoreFocus) {
            if (!sidebar || !sidebarBackdrop) return;

            sidebar.classList.toggle('-translate-x-full', !open);
            sidebarBackdrop.classList.toggle('hidden', !open);
            document.body.classList.toggle('overflow-hidden', open);
            setInert(sidebar, !open);
            setInert(document.getElementById('app-content'), open);
            sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
            sidebarToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open) {
                sidebarClose?.focus();
            } else if (restoreFocus && sidebarLastFocused?.focus) {
                sidebarLastFocused.focus();
            }
        }

        function toggleSidebar(force) {
            if (!sidebar || !isMobileSidebar()) return;

            var open = typeof force === 'boolean'
                ? force
                : sidebar.classList.contains('-translate-x-full');

            if (open) sidebarLastFocused = document.activeElement;
            setSidebarState(open, true);
        }
        window.toggleSidebar = toggleSidebar;

        function syncSidebarToViewport() {
            if (!sidebar || !sidebarBackdrop) return;

            if (isMobileSidebar()) {
                setSidebarState(false, false);
            } else {
                sidebar.classList.remove('-translate-x-full');
                sidebarBackdrop.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                setInert(sidebar, false);
                setInert(document.getElementById('app-content'), false);
                sidebar.removeAttribute('aria-hidden');
                sidebarToggle?.setAttribute('aria-expanded', 'false');
            }
        }

        document.addEventListener('keydown', function (event) {
            if (!isMobileSidebar() || !sidebar || sidebar.classList.contains('-translate-x-full')) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                toggleSidebar(false);
                return;
            }

            if (event.key !== 'Tab') return;

            var focusables = sidebar.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
            if (!focusables.length) return;
            var first = focusables[0];
            var last = focusables[focusables.length - 1];
            var active = document.activeElement;

            if (!sidebar.contains(active)) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus();
            } else if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        });

        // Close the drawer after tapping a nav link on mobile.
        document.querySelectorAll('#sidebar nav a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobileSidebar()) toggleSidebar(false);
            });
        });

        syncSidebarToViewport();
        window.addEventListener('resize', syncSidebarToViewport);

        // ---- Icon-rail minimize (desktop) ------------------------------
        (function () {
            var aside = document.getElementById('sidebar');
            var content = document.getElementById('app-content');
            var btn = document.getElementById('sidebar-minimize');
            if (!aside || !content || !btn) return;

            var W_FULL = 256;   // w-64
            var W_MINI = 68;    // icon rail
            var DESKTOP = 1024; // matches the lg: breakpoint in app.css

            function apply(mini) {
                // Below the lg breakpoint the sidebar is an off-canvas drawer
                // and the content column is not offset at all. Writing an
                // inline margin here would strand the page 68px to the right
                // with no visible control to undo it.
                var desktop = window.innerWidth >= DESKTOP;

                aside.dataset.minimized = desktop && mini ? 'true' : 'false';

                if (!desktop) {
                    aside.style.width = '';
                    content.style.marginLeft = '';
                    btn.querySelector('svg').style.transform = '';
                    return;
                }

                aside.style.width = (mini ? W_MINI : W_FULL) + 'px';
                content.style.marginLeft = (mini ? W_MINI : W_FULL) + 'px';
                // Labels, tooltips and sub-menu hiding are CSS-driven from the
                // data attribute — no per-element class toggling needed.
                // Chevron flips to point right when minimized.
                btn.querySelector('svg').style.transform = mini ? 'rotate(180deg)' : '';
                var label = mini ? 'Expand sidebar' : 'Minimize sidebar';
                btn.title = label;
                btn.setAttribute('aria-label', label);
                btn.setAttribute('aria-expanded', mini ? 'false' : 'true');
                try { localStorage.setItem('sidebar-min', mini ? '1' : '0'); } catch (e) {}
            }

            btn.addEventListener('click', function () {
                apply(aside.dataset.minimized !== 'true');
            });

            // Re-apply on resize so rotating a tablet or resizing a window
            // cannot leave a stale inline offset behind.
            window.addEventListener('resize', function () {
                apply(aside.dataset.minimized === 'true');
            });

            // Restore persisted state before first paint of interactions.
            var saved = null;
            try { saved = localStorage.getItem('sidebar-min'); } catch (e) {}
            if (saved === '1') apply(true);
            else apply(false);
        })();
    </script>
    @endunless
    @endif
</body>
</html>
