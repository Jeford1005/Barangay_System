@php
    // The settings dialog's section list - the single source for labels,
    // hints, icons and routes. Full settings pages carry only their content:
    // this rail is the section navigation (embed mode proves it), so there is
    // no second, page-level copy that could drift from it.
    $sections = [
        [
            'key' => 'users',
            'label' => 'User Accounts',
            'hint' => 'Access and roles',
            'route' => 'admin.users.index',
            'pattern' => 'admin.users.*',
            'icon' => 'users',
        ],
        [
            'key' => 'approvals',
            'label' => 'Account Approvals',
            'hint' => 'Resident applications',
            'route' => 'admin.approvals.index',
            'pattern' => 'admin.approvals.*',
            'icon' => 'check-badge',
        ],
        [
            'key' => 'mail',
            'label' => 'Mail and Notifications',
            'hint' => 'SMTP and delivery',
            'route' => 'admin.mail.health',
            'pattern' => 'admin.mail.*',
            'icon' => 'envelope',
        ],
        [
            'key' => 'audit',
            'label' => 'Audit and Security',
            'hint' => 'Activity and access',
            'route' => 'admin.audit-logs.index',
            'pattern' => 'admin.audit-logs.*',
            'icon' => 'clipboard-clock',
        ],
        [
            'key' => 'maintenance',
            'label' => 'System Maintenance',
            'hint' => 'Backups and health',
            'route' => 'admin.settings.maintenance',
            'pattern' => 'admin.settings.maintenance*',
            'icon' => 'archive-box',
        ],
    ];
@endphp

{{-- Dark section rail: slate-900 surface in the sidebar's nav-row language.
     The server marks the entry matching the parent page's route; once the
     dialog is open, settings-dialog.js re-marks it as the frame navigates
     (settings-tab-active). --}}
<nav class="flex flex-1 gap-1 overflow-x-auto p-2 md:flex-col md:overflow-x-visible md:overflow-y-auto" aria-label="Settings sections">
    @foreach ($sections as $section)
        @php
            $isCurrent = request()->routeIs($section['pattern']);
        @endphp
        <a href="{{ route($section['route']) }}" data-section-label="{{ $section['label'] }}" @if($isCurrent) aria-current="page" @endif class="nav-row shrink-0 {{ $isCurrent ? 'nav-row-active' : '' }}">
            <span class="nav-icon"><x-icon :name="$section['icon']" class="h-[18px] w-[18px]" /></span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-medium">{{ $section['label'] }}</span>
                <span class="mt-0.5 block truncate text-xs font-normal text-white/50">{{ $section['hint'] }}</span>
            </span>
        </a>
    @endforeach
</nav>
