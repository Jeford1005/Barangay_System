{{-- Sidebar navigation: operational links for office users, admin-only links for administrators. --}}
@php
    $user = auth()->user();
    $isAdmin = $user?->isAdmin() ?? false;
    $isStaff = $user?->isStaff() ?? false;
    $isOfficeUser = $isAdmin || $isStaff;
    $isFragment = request()->boolean('fragment');
    $pendingApprovals = $isAdmin && ! $isFragment ? App\Models\User::where('status', 'pending')->where('user_type', 'resident')->count() : 0;
    $pendingCertRequests = $isOfficeUser && ! $isFragment ? App\Models\CertificateRequest::pending()->count() : 0;
    $pendingResidentChanges = $isOfficeUser && ! $isFragment ? App\Models\ResidentRecordChange::where('status', 'Pending')->count() : 0;
    $pendingBadge = $pendingApprovals + $pendingCertRequests + $pendingResidentChanges;
@endphp

@auth
    @if ($isOfficeUser)
        <p class="nav-section"><span class="nav-section-index">01</span> Main</p>

        <a href="{{ route('dashboard') }}" title="Dashboard" @if(request()->routeIs('dashboard')) aria-current="page" @endif class="nav-row {{ request()->routeIs('dashboard') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="home-modern" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Dashboard</span>
        </a>
        <a href="{{ route('residents.index') }}" title="Residents" @if(request()->routeIs('residents.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('residents.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="residents" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Residents</span>
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
        <a href="{{ route('certificates.index') }}" title="Certificates" @if(request()->routeIs('certificates.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('certificates.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="printer" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Certificates</span>
        </a>
        @if ($isAdmin)
            <a href="{{ route('admin.certificate-types.index') }}" title="Certificate Catalog" @if(request()->routeIs('admin.certificate-types.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('admin.certificate-types.*') ? 'nav-row-active' : '' }}">
                <span class="nav-icon"><x-icon name="document-text" class="h-[18px] w-[18px]" /></span>
                <span class="sidebar-label truncate">Certificate Catalog</span>
            </a>
        @endif
        <a href="{{ route('admin.certificate-requests.index') }}" title="Certificate Requests" @if(request()->routeIs('admin.certificate-requests.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('admin.certificate-requests.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="inbox" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Certificate Requests</span>
            @if ($pendingCertRequests)
                <span class="nav-count sidebar-label">{{ $pendingCertRequests }}</span>
            @endif
        </a>
        <a href="{{ route('admin.resident-changes.index') }}" title="Resident Corrections" @if(request()->routeIs('admin.resident-changes.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('admin.resident-changes.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="document-text" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Resident Corrections</span>
            @if ($pendingResidentChanges)
                <span class="nav-count sidebar-label">{{ $pendingResidentChanges }}</span>
            @endif
        </a>
        <a href="{{ route('reports.index') }}" title="Reports" @if(request()->routeIs('reports.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('reports.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="document-text" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Reports</span>
        </a>
        <a href="{{ route('analytics.index') }}" title="Analytics" @if(request()->routeIs('analytics.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('analytics.*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="chart-bar" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Analytics</span>
        </a>

        @if ($isAdmin)
            <a href="{{ route('archive.index') }}" title="Archive" @if(request()->routeIs('archive.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('archive.*') ? 'nav-row-active' : '' }}">
                <span class="nav-icon"><x-icon name="archive-box" class="h-[18px] w-[18px]" /></span>
                <span class="sidebar-label truncate">Archive</span>
            </a>

            <p class="nav-section"><span class="nav-section-index">03</span> Administration</p>

            <a href="{{ route('admin.settings.index') }}" title="Settings" @if(request()->routeIs('admin.settings.*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('admin.settings.*') ? 'nav-row-active' : '' }}">
                <span class="nav-icon"><x-icon name="cog-6-tooth" class="h-[18px] w-[18px]" /></span>
                <span class="sidebar-label truncate">Settings</span>
                @if ($pendingBadge)
                    <span class="nav-count sidebar-label">{{ $pendingBadge }}</span>
                @endif
            </a>
        @endif
    @else
        <p class="nav-section"><span class="nav-section-index">01</span> Profile</p>

        <a href="{{ route('resident.portal') }}" title="My Profile" @if(request()->routeIs('resident.portal')) aria-current="page" @endif class="nav-row {{ request()->routeIs('resident.portal') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="user-circle" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">My Profile</span>
        </a>
        <a href="{{ route('resident.changes') }}" title="Profile Corrections" @if(request()->routeIs('resident.changes*')) aria-current="page" @endif class="nav-row {{ request()->routeIs('resident.changes*') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="pencil-square" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Profile Corrections</span>
        </a>

        <p class="nav-section"><span class="nav-section-index">02</span> Requests</p>

        <a href="{{ route('resident.requests') }}" title="My Requests" @if(request()->routeIs('resident.requests')) aria-current="page" @endif class="nav-row {{ request()->routeIs('resident.requests') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="document-text" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">My Requests</span>
        </a>
        <a href="{{ route('resident.blotter') }}" title="Report an Incident" @if(request()->routeIs('resident.blotter')) aria-current="page" @endif class="nav-row {{ request()->routeIs('resident.blotter') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="clipboard-document-list" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Report an Incident</span>
        </a>
        <a href="{{ route('resident.welfare') }}" title="Request Assistance" @if(request()->routeIs('resident.welfare')) aria-current="page" @endif class="nav-row {{ request()->routeIs('resident.welfare') ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon name="shield-check" class="h-[18px] w-[18px]" /></span>
            <span class="sidebar-label truncate">Request Assistance</span>
        </a>
    @endif
@endauth
