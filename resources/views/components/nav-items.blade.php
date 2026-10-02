{{-- Sidebar navigation: operational links for office users, admin-only links for administrators. --}}
@php
    $user = auth()->user();
    $isAdmin = $user?->isAdmin() ?? false;
    $isOfficeUser = $user?->isOfficeUser() ?? false;
    $isFragment = request()->boolean('fragment');
    // Every settings section lives under Settings in this sidebar — none has
    // an entry of its own — so the row must light up wherever the dialog is
    // parked, not only on maintenance.
    $onSettingsSection = request()->routeIs('admin.settings.*', 'admin.users.*', 'admin.approvals.*', 'admin.mail.*', 'admin.audit-logs.*');
    // Sidebar badges share one request-scoped source instead of three
    // per-render counts. Reuse badges already computed for this request when
    // present, otherwise compute once, memoize on the container, and cache
    // briefly per user so every sidebar render recounts nothing.
    if (app()->bound('sidebar.badges')) {
        $sidebarBadges = app('sidebar.badges');
    } elseif ($isFragment || ! $isOfficeUser) {
        // Fragments skip chrome data; guests/residents never see these badges
        // (admins are office users, so the approvals badge stays covered).
        $sidebarBadges = ['approvals' => 0, 'certRequests' => 0, 'residentChanges' => 0];
    } else {
        $sidebarBadges = \Illuminate\Support\Facades\Cache::remember(
            'sidebar.badges.'.auth()->id(),
            now()->addSeconds(30),
            function () use ($isAdmin) {
                return [
                    'approvals' => $isAdmin ? App\Models\User::where('status', 'pending')->where('user_type', 'resident')->count() : 0,
                    'certRequests' => App\Models\CertificateRequest::pending()->count(),
                    'residentChanges' => App\Models\ResidentRecordChange::where('status', 'Pending')->count(),
                ];
            }
        );
        app()->instance('sidebar.badges', $sidebarBadges);
    }
    $pendingApprovals = $sidebarBadges['approvals'];
    $pendingCertRequests = $sidebarBadges['certRequests'];
    $pendingResidentChanges = $sidebarBadges['residentChanges'];
    // Merged modules carry their own queues now: Residents shows corrections,
    // Certificates shows requests, and Settings keeps only account approvals.
@endphp

@auth
    @if ($isOfficeUser)
        <p class="nav-section"><span class="nav-section-index">01</span> Main</p>

        <a href="{{ route('dashboard') }}" title="Dashboard" @if(request()->routeIs('dashboard')) aria-current="page" @endif class="nav-row {{ request()->routeIs('dashboard') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="home-modern" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Dashboard</span>
        </a>
        <a href="{{ route('residents.index') }}" title="Residents" @if(request()->routeIs('residents.*', 'admin.resident-changes.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('residents.*', 'admin.resident-changes.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="residents" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Residents</span>
            @if ($pendingResidentChanges)
                <span class="nav-count sidebar-label">{{ $pendingResidentChanges }}</span>
            @endif
        </a>
        <a href="{{ route('households.index') }}" title="Households" @if(request()->routeIs('households.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('households.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="households" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Households</span>
        </a>
        <a href="{{ route('puroks.index') }}" title="Puroks" @if(request()->routeIs('puroks.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('puroks.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="map-pin" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Puroks</span>
        </a>

        <p class="nav-section"><span class="nav-section-index">02</span> Governance</p>

        <a href="{{ route('blotter.index') }}" title="Blotter" @if(request()->routeIs('blotter.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('blotter.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="clipboard-document-list" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Blotter</span>
        </a>
        <a href="{{ route('welfare.index') }}" title="Welfare" @if(request()->routeIs('welfare.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('welfare.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="shield-check" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Welfare</span>
        </a>
        <a href="{{ route('certificates.index') }}" title="Certificates" @if(request()->routeIs('certificates.*', 'admin.certificate-requests.*', 'admin.certificate-types.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('certificates.*', 'admin.certificate-requests.*', 'admin.certificate-types.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="printer" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Certificates</span>
            @if ($pendingCertRequests)
                <span class="nav-count sidebar-label">{{ $pendingCertRequests }}</span>
            @endif
        </a>
        <a href="{{ route('reports.index') }}" title="Reports" @if(request()->routeIs('reports.*', 'analytics.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('reports.*', 'analytics.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="document-text" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Reports</span>
        </a>
        <a href="{{ route('two-factor.settings') }}" title="Two-factor authentication" @if(request()->routeIs('two-factor.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('two-factor.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="shield-check" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Two-factor auth</span>
        </a>

        @if ($isAdmin)
            <p class="nav-section"><span class="nav-section-index">03</span> Administration</p>

            <a href="{{ route('archive.index') }}" title="Archive" @if(request()->routeIs('archive.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('archive.*') ? 'nav-row-active' : '' }}">
                <span class="nav-icon"><x-icon name="archive-box" class="h-[18px] w-[18px]" /></span>
                <span class="sidebar-label truncate">Archive</span>
            </a>

            <a href="{{ route('admin.settings.index') }}" title="Settings" data-settings-open @if($onSettingsSection) aria-current="page" @endif class="nav-row {{ $onSettingsSection ? 'nav-row-active' : '' }}">
                <span class="nav-icon"><x-icon name="cog-6-tooth" class="h-[18px] w-[18px]" /></span>
                <span class="sidebar-label truncate">Settings</span>
                @if ($pendingApprovals)
                    <span class="nav-count sidebar-label">{{ $pendingApprovals }}</span>
                @endif
            </a>
        @endif
    @else
        <p class="nav-section"><span class="nav-section-index">01</span> Profile</p>

        <a href="{{ route('resident.portal') }}" title="Profile" @if(request()->routeIs('resident.portal')) aria-current="page" @endif class="nav-row {{ request()->routeIs('resident.portal') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="user-circle" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Profile</span>
        </a>
        <p class="nav-section"><span class="nav-section-index">02</span> Requests</p>

        <a href="{{ route('resident.requests') }}" title="Request" @if(request()->routeIs('resident.requests')) aria-current="page" @endif class="nav-row {{ request()->routeIs('resident.requests') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="document-text" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Request</span>
        </a>
        <a href="{{ route('resident.blotter') }}" title="Report Incident" @if(request()->routeIs('resident.blotter')) aria-current="page" @endif class="nav-row {{ request()->routeIs('resident.blotter') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="clipboard-document-list" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Report Incident</span>
        </a>
        <a href="{{ route('resident.welfare') }}" title="Request Assistance" @if(request()->routeIs('resident.welfare')) aria-current="page" @endif class="nav-row {{ request()->routeIs('resident.welfare') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="shield-check" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Request Assistance</span>
        </a>

        <p class="nav-section"><span class="nav-section-index">03</span> Barangay</p>

        <a href="{{ route('resident.officials') }}" title="Officials" @if(request()->routeIs('resident.officials')) aria-current="page" @endif class="nav-row {{ request()->routeIs('resident.officials') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="building-office-2" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Officials</span>
        </a>
    @endif
@endauth
