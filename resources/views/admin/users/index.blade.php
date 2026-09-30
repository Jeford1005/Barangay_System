<x-app-layout>
@section('page_header')
    <x-page-header title="User Accounts" subtitle="{{ $users->total() }} account{{ $users->total() === 1 ? '' : 's' }} in the directory">
    </x-page-header>
@endsection

@section('content')
<x-settings-shell>
<div class="space-y-4">
    <form method="GET" action="{{ route('admin.users.index') }}" class="module-toolbar-sticky rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:flex-nowrap lg:items-center">
            <div class="module-toolbar-filters module-toolbar-filters--admin min-w-0 flex-1">
            <div class="min-w-0 flex-1">
                <label for="user-search" class="sr-only">Search accounts</label>
                <input id="user-search" name="search" type="search" maxlength="100" value="{{ $search }}" placeholder="Search by name or email" class="min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
            </div>
            <div class="w-full">
                <label for="user-role" class="sr-only">Filter by role</label>
                <select id="user-role" name="role" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                    <option value="">All roles</option>
                    <option value="admin" @selected($role === 'admin')>Administrator</option>
                    <option value="staff" @selected($role === 'staff')>Staff</option>
                    <option value="official" @selected($role === 'official')>Official</option>
                    <option value="resident" @selected($role === 'resident')>Resident</option>
                </select>
            </div>
            <div class="w-full">
                <label for="user-status" class="sr-only">Filter by status</label>
                <select id="user-status" name="status" onchange="this.form.submit()" class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                    <option value="">All Status</option>
                    @foreach (\App\Models\User::statusFilterOptions() as $optionValue => $optionLabel)
                        <option value="{{ $optionValue }}" @selected($status === $optionValue)>{{ $optionLabel }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit" class="btn btn-neutral">Filter</button></noscript>
            <div class="flex shrink-0 items-center gap-2">
                @if ($search !== '' || $role !== '' || $status !== '')
                    <a href="{{ route('admin.users.index') }}" class="btn btn-neutral">Reset</a>
                @endif
            </div>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($users->isEmpty())
            <div class="px-6 py-14 text-center">
                <x-icon name="users" class="mx-auto h-10 w-10 text-slate-300" />
                <h2 class="mt-3 text-sm font-semibold text-slate-900">No user accounts found</h2>
                <p class="mt-1 text-sm text-slate-500">Try clearing the filters or check the Account Approvals queue.</p>
            </div>
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <caption class="sr-only">User accounts</caption>
                    <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">User</th>
                            <th scope="col" class="px-4 py-3 font-medium">Role</th>
                            <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            <th scope="col" class="hidden px-4 py-3 font-medium print:table-cell md:table-cell">Linked resident</th>
                            <th scope="col" class="hidden px-4 py-3 font-medium print:table-cell md:table-cell">Registered</th>
                            <th scope="col" class="no-print px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($users as $user)
                            @php
                                $displayStatus = $user->statusLabel();
                                // Local *-100 mapping so the directory pill matches every
                                // other status badge in the app (Requested/suspended slate,
                                // pending amber, approved emerald, rejected red).
                                $statusClasses = $user->isSuspended()
                                    ? 'bg-slate-100 text-slate-700'
                                    : match ($user->status) {
                                        'approved' => 'bg-emerald-100 text-emerald-800',
                                        'pending' => 'bg-amber-100 text-amber-800',
                                        default => 'bg-red-100 text-red-800',
                                    };
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.users.show', $user) }}" class="inline-flex min-h-6 items-center font-medium text-sky-700 hover:underline">{{ $user->name }}</a>
                                    <span class="block text-xs text-slate-500">{{ $user->email }}</span>
                                    <span class="mt-1 block text-xs text-slate-500 md:hidden">{{ e($user->residentProfile?->full_name ?? '—') }} &middot; {{ $user->created_at?->format('M j, Y') ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $user->roleLabel() }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClasses }}">{{ $displayStatus }}</span>
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 print:table-cell md:table-cell">{{ $user->residentProfile?->full_name ?? '—' }}</td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-slate-600 print:table-cell md:table-cell">{{ $user->created_at?->format('M j, Y') }}</td>
                                <td class="no-print px-4 py-3 text-right">
                                    @if ($user->status === 'pending' && $user->user_type === 'resident')
                                        <a href="{{ route('admin.approvals.index') }}" class="btn btn-outline btn-row">Review</a>
                                    @else
                                        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline btn-row">View</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $users->withQueryString()->links() }}</div>
        @endif
    </div>
</div>
</x-settings-shell>
@endsection
</x-app-layout>
