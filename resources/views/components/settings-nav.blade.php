@props([
    'current' => null,
    'variant' => 'page',
])

@php
    // No Overview hub: this list IS the settings home, in both variants.
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

{{-- Shared section navigation: the settings page's light sidebar and the
     settings dialog's dark rail render this same list, so the two can never
     drift. variant="rail" wears the app's own nav-row language (slate-900
     surface, sky active marker); the default variant keeps the page shell. --}}
@if ($variant === 'rail')
<nav class="flex flex-1 gap-1 overflow-x-auto p-2 md:flex-col md:overflow-x-visible md:overflow-y-auto" aria-label="Settings sections">
    @foreach ($sections as $section)
        @php
            $isCurrent = $current
                ? $current === $section['key']
                : request()->routeIs($section['pattern']);
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
@else
<nav class="settings-tabs mt-3 flex gap-1 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible" aria-label="Settings sections">
    @foreach ($sections as $section)
        @php
            $isCurrent = $current
                ? $current === $section['key']
                : request()->routeIs($section['pattern']);
        @endphp
        <a href="{{ route($section['route']) }}" @if($isCurrent) aria-current="page" @endif class="settings-tab {{ $isCurrent ? 'settings-tab-active' : '' }}">
            <span class="settings-tab-icon"><x-icon :name="$section['icon']" class="h-4 w-4" /></span>
            <span class="min-w-0">
                <span class="block truncate text-sm font-medium">{{ $section['label'] }}</span>
                <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $section['hint'] }}</span>
            </span>
        </a>
    @endforeach
</nav>
@endif
