@extends('layouts.app')

@section('title', 'Accounts')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Accounts</h1>
            <p class="mt-1 text-sm text-slate-500">
                Create staff and official accounts, approve residents, and manage access.
            </p>
        </div>

        <button type="button"
                data-open-modal="account-modal"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
            + New account
        </button>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Awaiting approval</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $counts['pending'] }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Active</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ $counts['active'] }}</p>
        </div>
        <div class="rounded-xl border border-red-200 bg-red-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Suspended</p>
            <p class="mt-1 text-2xl font-semibold text-red-900">{{ $counts['suspended'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rejected</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $counts['rejected'] }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Account</th>
                    <th class="px-5 py-3">Role</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Joined</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $account)
                    @php($isSelf = $account->id === auth()->id())
                    <tr class="@if($account->isPending()) bg-amber-50/50 @endif">
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-slate-900">
                                {{ $account->name }}
                                @if ($isSelf)
                                    <span class="ml-1 text-xs font-normal text-slate-400">(you)</span>
                                @endif
                            </p>
                            <p class="text-slate-500">{{ $account->email }}</p>
                        </td>

                        <td class="px-5 py-3.5">
                            @if ($isSelf)
                                <span class="text-slate-600">{{ ucfirst($account->role) }}</span>
                            @else
                                <form method="POST" action="{{ route('admin.accounts.role', $account) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role"
                                            class="rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                                        @foreach (['admin', 'staff', 'resident'] as $role)
                                            <option value="{{ $role }}" @selected($account->role === $role)>{{ ucfirst($role) }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit"
                                            class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                        Save
                                    </button>
                                </form>
                            @endif
                        </td>

                        <td class="px-5 py-3.5">
                            @switch($account->status)
                                @case('active')
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                                    @break
                                @case('pending')
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Pending</span>
                                    @break
                                @case('suspended')
                                    <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">Suspended</span>
                                    @break
                                @default
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Rejected</span>
                            @endswitch
                        </td>

                        <td class="px-5 py-3.5 text-slate-500">{{ $account->created_at->format('M j, Y') }}</td>

                        <td class="px-5 py-3.5">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($account->isPending())
                                    <form method="POST" action="{{ route('admin.accounts.approve', $account) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-700">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.accounts.reject', $account) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">Reject</button>
                                    </form>
                                @elseif ($account->isActive() && ! $isSelf)
                                    <form method="POST" action="{{ route('admin.accounts.suspend', $account) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50">Suspend</button>
                                    </form>
                                @elseif (in_array($account->status, ['suspended', 'rejected'], true))
                                    <form method="POST" action="{{ route('admin.accounts.reactivate', $account) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-sky-700">Reactivate</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">&mdash;</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">No accounts found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>

    {{-- ── New account dialog — centered on screen ── --}}
    <div id="account-modal"
         data-modal
         role="dialog"
         aria-modal="true"
         aria-labelledby="account-modal-title"
         aria-hidden="true"
         class="fixed inset-0 z-40 hidden">
        <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" data-close-modal></div>

        <div class="absolute inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
                <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7">

                    <button type="button"
                            data-close-modal
                            aria-label="Close dialog"
                            class="absolute right-4 top-4 rounded-md p-1.5 text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" width="18" height="18">
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>
                    </button>

                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-400">Administration</p>
                    <h2 id="account-modal-title" class="mt-2 text-xl font-semibold text-slate-900">New account</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500">
                        Create an account for a barangay staff member, official or resident. The account is active immediately.
                    </p>

                    <form method="POST" action="{{ route('admin.accounts.store') }}"
                          data-submit-loading data-loading-label="Creating…"
                          class="mt-6 space-y-4">
                        @csrf

                        <div>
                            <label for="name" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Full name</label>
                            <input id="name"
                                   type="text"
                                   name="name"
                                   value="{{ old('name') }}"
                                   required
                                   maxlength="150"
                                   autocomplete="off"
                                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('name') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                            @error('name')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="account_email" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Email address</label>
                            <input id="account_email"
                                   type="email"
                                   name="email"
                                   value="{{ old('email') }}"
                                   required
                                   maxlength="150"
                                   autocomplete="off"
                                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('email') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                            @error('email')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="account_password" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Password</label>
                            <input id="account_password"
                                   type="password"
                                   name="password"
                                   required
                                   minlength="8"
                                   maxlength="100"
                                   autocomplete="new-password"
                                   placeholder="At least 8 characters"
                                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('password') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                            @error('password')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="role" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Role</label>
                            <select id="role"
                                    name="role"
                                    required
                                    class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                <option value="staff" @selected(old('role') === 'staff')>Staff — barangay office work</option>
                                <option value="admin" @selected(old('role') === 'admin')>Administrator — full access</option>
                                <option value="resident" @selected(old('role') === 'resident')>Resident — portal only</option>
                            </select>
                            @error('role')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                                class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 disabled:cursor-wait disabled:opacity-70">
                            Create account
                        </button>
                    </form>

                    <div class="mt-5 border-t border-slate-100 pt-4 text-center">
                        <button type="button" data-close-modal class="text-sm text-slate-500 transition hover:text-slate-800">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
