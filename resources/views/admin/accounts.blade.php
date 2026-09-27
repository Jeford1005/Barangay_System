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
                            @if ($account->residentApplication)
                                <button type="button"
                                        data-open-modal="application-modal-{{ $account->id }}"
                                        class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium text-sky-600 transition hover:text-sky-700 hover:underline">
                                    Application &middot;
                                    @switch($account->residentApplication->status)
                                        @case('Pending')
                                            <span class="rounded-full bg-amber-100 px-2 py-px text-[11px] font-semibold text-amber-800">Pending</span>
                                            @break
                                        @case('Approved')
                                            <span class="rounded-full bg-emerald-100 px-2 py-px text-[11px] font-semibold text-emerald-800">Approved</span>
                                            @break
                                        @case('Rejected')
                                            <span class="rounded-full bg-red-100 px-2 py-px text-[11px] font-semibold text-red-800">Rejected</span>
                                            @break
                                        @default
                                            <span class="rounded-full bg-slate-100 px-2 py-px text-[11px] font-semibold text-slate-600">Cancelled</span>
                                    @endswitch
                                </button>
                            @endif
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
                                    <button type="button"
                                            data-open-modal="reject-modal-{{ $account->id }}"
                                            class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">Reject</button>
                                @elseif ($account->isActive() && ! $isSelf)
                                    @if ($account->role === 'resident'
                                        && $account->resident_id === null
                                        && $account->residentApplication !== null
                                        && in_array($account->residentApplication->status, ['Pending', 'Approved'], true))
                                        <form method="POST" action="{{ route('admin.accounts.profile-from-application', $account) }}">
                                            @csrf
                                            <button type="submit" class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-sky-700">Create profile</button>
                                        </form>
                                    @endif
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

    {{-- ── Per-row dialogs: reject reason + submitted application details ── --}}
    @foreach ($users as $account)
        @if ($account->isPending())
            <div id="reject-modal-{{ $account->id }}"
                 data-modal
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="reject-modal-title-{{ $account->id }}"
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
                            <h2 id="reject-modal-title-{{ $account->id }}" class="mt-2 text-xl font-semibold text-slate-900">Reject application</h2>
                            <p class="mt-2 text-sm leading-relaxed text-slate-500">
                                Reject the registration of <span class="font-medium text-slate-700">{{ $account->name }}</span>
                                ({{ $account->email }}). The reason is recorded with the application.
                            </p>

                            <form method="POST" action="{{ route('admin.accounts.reject', $account) }}"
                                  data-submit-loading data-loading-label="Rejecting…"
                                  class="mt-5 space-y-4">
                                @csrf

                                <div>
                                    <label for="reject-reason-{{ $account->id }}" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Reason <span class="font-normal normal-case text-slate-400">(optional)</span></label>
                                    <textarea id="reject-reason-{{ $account->id }}"
                                              name="reason"
                                              rows="3"
                                              minlength="5"
                                              maxlength="500"
                                              placeholder="e.g. Incomplete requirements — please visit the barangay hall."
                                              class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"></textarea>
                                    <p class="mt-1.5 text-xs text-slate-400">At least 5 characters when provided.</p>
                                </div>

                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" data-close-modal class="rounded-md px-4 py-2 text-sm font-medium text-slate-500 transition hover:text-slate-800">Cancel</button>
                                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-200 disabled:cursor-wait disabled:opacity-70">Reject application</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($account->residentApplication)
            <div id="application-modal-{{ $account->id }}"
                 data-modal
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="application-modal-title-{{ $account->id }}"
                 aria-hidden="true"
                 class="fixed inset-0 z-40 hidden">
                <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" data-close-modal></div>

                <div class="absolute inset-0 overflow-y-auto">
                    <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
                        <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-7">
                            <button type="button"
                                    data-close-modal
                                    aria-label="Close dialog"
                                    class="absolute right-4 top-4 rounded-md p-1.5 text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" width="18" height="18">
                                    <path d="M18 6 6 18M6 6l12 12"/>
                                </svg>
                            </button>

                            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-400">Resident portal</p>
                            <h2 id="application-modal-title-{{ $account->id }}" class="mt-2 text-xl font-semibold text-slate-900">Registration application</h2>

                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                @switch($account->residentApplication->status)
                                    @case('Pending')
                                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 font-semibold text-amber-800">Pending review</span>
                                        @break
                                    @case('Approved')
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 font-semibold text-emerald-800">Approved</span>
                                        @break
                                    @case('Rejected')
                                        <span class="rounded-full bg-red-100 px-2.5 py-0.5 font-semibold text-red-800">Rejected</span>
                                        @break
                                    @default
                                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 font-semibold text-slate-600">Cancelled</span>
                                @endswitch
                                <span>Submitted {{ $account->residentApplication->created_at->format('M j, Y g:i A') }}</span>
                                @if ($account->residentApplication->reviewed_at && $account->residentApplication->reviewer)
                                    <span>&middot; Reviewed by {{ $account->residentApplication->reviewer->name }} on {{ $account->residentApplication->reviewed_at->format('M j, Y') }}</span>
                                @endif
                            </div>

                            <dl class="mt-5 grid grid-cols-2 gap-x-5 gap-y-3.5 text-sm">
                                <div class="col-span-2">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Full name</dt>
                                    <dd class="mt-0.5 text-slate-900">{{ trim(implode(' ', array_filter([$account->residentApplication->first_name, $account->residentApplication->middle_name, $account->residentApplication->last_name, $account->residentApplication->suffix]))) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Birth date</dt>
                                    <dd class="mt-0.5 text-slate-900">{{ $account->residentApplication->birth_date->format('M j, Y') }} <span class="text-slate-500">({{ $account->residentApplication->birth_date->age }} yrs old)</span></dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Sex</dt>
                                    <dd class="mt-0.5 text-slate-900">{{ $account->residentApplication->sex }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Civil status</dt>
                                    <dd class="mt-0.5 text-slate-900">{{ $account->residentApplication->civil_status }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Mobile number</dt>
                                    <dd class="mt-0.5 text-slate-900">{{ $account->residentApplication->phone_number ?: '—' }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Home address</dt>
                                    <dd class="mt-0.5 text-slate-900">{{ $account->residentApplication->address }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Purok</dt>
                                    <dd class="mt-0.5 text-slate-900">{{ $account->residentApplication->purok ? $account->residentApplication->purok->code.' · '.$account->residentApplication->purok->name : '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Household</dt>
                                    <dd class="mt-0.5 text-slate-900">{{ $account->residentApplication->household ? $account->residentApplication->household->household_number : '—' }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Sign-in email</dt>
                                    <dd class="mt-0.5 text-slate-900">{{ $account->residentApplication->email }}</dd>
                                </div>
                            </dl>

                            @if ($account->residentApplication->review_note)
                                <div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                    <span class="font-semibold">Review note:</span> {{ $account->residentApplication->review_note }}
                                </div>
                            @endif

                            <div class="mt-6 flex justify-end border-t border-slate-100 pt-4">
                                <button type="button" data-close-modal class="rounded-md px-4 py-2 text-sm font-medium text-slate-500 transition hover:text-slate-800">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

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
