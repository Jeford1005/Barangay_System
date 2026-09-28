@props([
    'current' => null,
])

@php
    $sections = [
        [
            'key' => 'overview',
            'label' => 'Overview',
            'hint' => 'Settings home',
            'route' => 'admin.settings.index',
            'pattern' => 'admin.settings.index',
            'icon' => 'cog-6-tooth',
        ],
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

<div class="mx-auto max-w-6xl">
    <div class="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:items-start">
        <aside class="border-b border-slate-200 pb-4 lg:border-b-0 lg:border-r lg:pb-0 lg:pr-5">
            <p class="px-2 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Settings</p>
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
        </aside>

        <div class="min-w-0">
            {{ $slot }}
        </div>
    </div>
</div>
