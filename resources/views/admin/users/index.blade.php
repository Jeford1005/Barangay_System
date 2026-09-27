<x-app-layout>
@section('page_header')
    <x-page-header title="User Accounts" subtitle="{{ $users->total() }} account{{ $users->total() === 1 ? '' : 's' }} in the directory" />
@endsection

@section('content')
<x-settings-shell current="users">
<div class="space-y-4">
    <form method="GET" action="{{ route('admin.users.index') }}" class="module-toolbar-sticky rounded-xl border border-neutral-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:flex-nowrap lg:items-center">
            <div class="module-toolbar-filters module-toolbar-filters--admin min-w-0 flex-1">
            <div class="min-w-0 flex-1">
                <label for="user-search" class="sr-only">Search accounts</label>
                <input id="user-search" name="search" type="search" maxlength="100" value="{{ $search }}" placeholder="Search by name or email" class="min-h-10 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="w-full">
                <label for="user-role" class="sr-only">Filter by role</label>
                <select id="user-role" name="role" class="min-h-10 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">All roles</option>
                    <option value="admin" @selected($role === 'admin')>Administrator</option>
                    <option value="staff" @selected($role === 'staff')>Staff</option>
                    <option value="resident" @selected($role === 'resident')>Resident</option>
                </select>
            </div>
            <div class="w-full">
                <label for="user-status" class="sr-only">Filter by status</label>
                <select id="user-status" name="status" class="min-h-10 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="approved" @selected($status === 'approved')>Approved</option>
                    <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                    <option value="suspended" @selected($status === 'suspended')>Suspended</option>
                </select>
            </div>
            <div class="flex shrink-0 gap-2">
                <button type="submit" class="inline-flex min-h-10 items-center rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-blue-500">Filter</button>
                @if ($search !== '' || $role !== '' || $status !== '')
                    <a href="{{ route('admin.users.index') }}" class="inline-flex min-h-10 items-center rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-blue-500">Reset</a>
                @endif
            </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-neutral-200 pt-3 lg:flex-nowrap lg:ml-3 lg:border-l lg:border-t-0 lg:pl-4 lg:pt-0">
                <a href="{{ route('admin.approvals.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-2 text-sm font-medium text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Account Approvals
                    @if ($pendingCount)
                        <span class="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">{{ $pendingCount }}</span>
                    @endif
                </a>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
        @if ($users->isEmpty())
            <div class="px-6 py-14 text-center">
                <x-icon name="users" class="mx-auto h-10 w-10 text-neutral-300" />
                <h2 class="mt-3 text-sm font-semibold text-neutral-900">No user accounts found</h2>
                <p class="mt-1 text-sm text-neutral-500">Try clearing the filters or check the Account Approvals queue.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <caption class="sr-only">User accounts</caption>
                    <thead class="bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">User</th>
                            <th scope="col" class="px-4 py-3 font-medium">Role</th>
                            <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            <th scope="col" class="hidden px-4 py-3 font-medium md:table-cell">Linked resident</th>
                            <th scope="col" class="hidden px-4 py-3 font-medium md:table-cell">Registered</th>
                            <th scope="col" class="no-print px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($users as $user)
                            @php
                                $displayStatus = $user->suspended_at ? 'Suspended' : ucfirst($user->status);
                                $statusClasses = $user->suspended_at
                                    ? 'bg-slate-100 text-slate-700'
                                    : match ($user->status) {
                                        'approved' => 'bg-green-50 text-green-700',
                                        'pending' => 'bg-amber-50 text-amber-700',
                                        default => 'bg-red-50 text-red-700',
                                    };
                            @endphp
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.users.show', $user) }}" class="font-medium text-blue-700 hover:underline">{{ $user->name }}</a>
                                    <span class="block text-xs text-neutral-500">{{ $user->email }}</span>
                                </td>
                                <td class="px-4 py-3 text-neutral-700">{{ $user->roleLabel() }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClasses }}">{{ $displayStatus }}</span>
                                </td>
                                <td class="hidden px-4 py-3 text-neutral-600 md:table-cell">{{ $user->residentProfile?->full_name ?? '—' }}</td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-neutral-600 md:table-cell">{{ $user->created_at?->format('M j, Y') }}</td>
                                <td class="no-print px-4 py-3 text-right">
                                    @if ($user->status === 'pending' && $user->user_type === 'resident')
                                        <a href="{{ route('admin.approvals.index') }}" class="inline-flex min-h-9 items-center rounded-md border border-blue-200 bg-white px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500">Review</a>
                                    @else
                                        <a href="{{ route('admin.users.show', $user) }}" class="inline-flex min-h-9 items-center rounded-md border border-blue-200 bg-white px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500">View</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-neutral-200 px-4 py-3">{{ $users->links() }}</div>
        @endif
    </div>
</div>
</x-settings-shell>
@endsection
</x-app-layout>
