<x-app-layout>
@section('page_header')
    <x-page-header title="Settings" subtitle="System administration and access controls" />
@endsection

@section('content')
<x-settings-shell current="overview">
    <section class="border-b border-neutral-200 pb-6">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Settings workspace</p>
        <h2 class="mt-2 text-xl font-semibold tracking-tight text-neutral-900">Keep the system organized and accountable.</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">Choose an area from the settings navigation. Account access, operational approvals, communication, audit history, and system care stay together without crowding the main application navigation.</p>
    </section>

    <section class="divide-y divide-neutral-200">
        <a href="{{ route('admin.users.index') }}" class="settings-row">
            <span class="settings-row-icon"><x-icon name="users" class="h-5 w-5" /></span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold">User Accounts</span>
                <span class="mt-1 block text-sm text-neutral-500">Manage existing accounts, roles, suspensions, and password-reset delivery.</span>
            </span>
        </a>

        <a href="{{ route('admin.approvals.index') }}" class="settings-row">
            <span class="settings-row-icon"><x-icon name="check-badge" class="h-5 w-5" /></span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold">Account Approvals</span>
                <span class="mt-1 block text-sm text-neutral-500">Review resident applications before they can sign in.</span>
            </span>
            @if ($pendingApprovals)
                <span class="settings-count">{{ $pendingApprovals }} pending</span>
            @endif
        </a>

        <a href="{{ route('admin.mail.health') }}" class="settings-row">
            <span class="settings-row-icon"><x-icon name="envelope" class="h-5 w-5" /></span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold">Mail and Notifications</span>
                <span class="mt-1 block text-sm text-neutral-500">Check SMTP delivery and send a test message for password resets.</span>
            </span>
        </a>

        <a href="{{ route('admin.audit-logs.index') }}" class="settings-row">
            <span class="settings-row-icon"><x-icon name="clipboard-clock" class="h-5 w-5" /></span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold">Audit and Security</span>
                <span class="mt-1 block text-sm text-neutral-500">Review account actions, access changes, and password-reset events.</span>
            </span>
        </a>

        <a href="{{ route('admin.settings.maintenance') }}" class="settings-row">
            <span class="settings-row-icon"><x-icon name="cog-6-tooth" class="h-5 w-5" /></span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold">System Maintenance</span>
                <span class="mt-1 block text-sm text-neutral-500">Create database backups, review service status, clear caches, and check system requirements.</span>
            </span>
        </a>
    </section>
</x-settings-shell>
@endsection
</x-app-layout>
