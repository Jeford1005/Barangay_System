<x-app-layout>
@section('page_header')
    <x-page-header title="{{ $user->name }}" subtitle="User account details and access history">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline">All accounts</a>
            <x-primary-action variant="update" :href="route('admin.users.edit', $user)">Edit details</x-primary-action>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
<div class="space-y-6">
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <p class="font-semibold">Unable to complete that action.</p>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Account overview</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Name</dt>
                    <dd class="mt-1 font-medium text-slate-900">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</dt>
                    <dd class="mt-1 break-all text-slate-800">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Role</dt>
                    <dd class="mt-1 text-slate-800">{{ $user->roleLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Account status</dt>
                    <dd class="mt-1">
                        @php
                            // Same *-100 scale as the directory and every other badge.
                            $accountBadge = $user->isSuspended()
                                ? 'bg-slate-100 text-slate-700'
                                : match ($user->status) {
                                    'approved' => 'bg-emerald-100 text-emerald-800',
                                    'pending' => 'bg-amber-100 text-amber-800',
                                    default => 'bg-red-100 text-red-800',
                                };
                        @endphp
                        <span class="inline-flex rounded-full {{ $accountBadge }} px-2 py-0.5 text-xs font-medium">{{ $user->statusLabel() }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Registered</dt>
                    <dd class="mt-1 text-slate-800">{{ $user->created_at?->format('M j, Y g:i A') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Approved</dt>
                    <dd class="mt-1 text-slate-800">{{ $user->approved_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Reviewed by</dt>
                    <dd class="mt-1 text-slate-800">{{ $user->reviewer?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Suspended by</dt>
                    <dd class="mt-1 text-slate-800">{{ $user->suspender?->name ?? '—' }}</dd>
                </div>
            </dl>

            @if ($user->suspension_reason)
                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
                    <p class="font-medium text-slate-800">Suspension reason</p>
                    <p class="mt-1 text-slate-700">{{ $user->suspension_reason }}</p>
                </div>
            @endif

            @if ($user->rejection_reason && $user->status === 'rejected')
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm">
                    <p class="font-medium text-red-800">Rejection reason</p>
                    <p class="mt-1 text-red-700">{{ $user->rejection_reason }}</p>
                </div>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Linked resident profile</h2>
            @if ($user->residentProfile)
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Full name</dt>
                        <dd class="mt-1 text-slate-800">{{ $user->residentProfile->full_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Record status</dt>
                        <dd class="mt-1 text-slate-800">{{ $user->residentProfile->status }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Purok</dt>
                        <dd class="mt-1 text-slate-800">{{ $user->residentProfile->purok?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Household</dt>
                        <dd class="mt-1 text-slate-800">{{ $user->residentProfile->household?->household_code ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Address</dt>
                        <dd class="mt-1 text-slate-800">{{ $user->residentProfile->address ?? '—' }}</dd>
                    </div>
                </dl>
                <form method="POST" action="{{ route('admin.users.resident-unlink', $user) }}" class="mt-5 border-t border-slate-200 pt-4" data-confirm="Unlink this resident profile and suspend the account?" data-confirm-title="Unlink resident profile" data-confirm-accept="Unlink" data-confirm-icon="x-mark">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">Unlink and suspend account</button>
                </form>
            @else
                <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">No resident profile is linked to this account.</p>
            @endif
        </section>
    </div>

    @if ($user->user_type === 'resident')
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Resident account link</h2>
            <p class="mt-1 text-sm text-slate-500">Link this login account to the correct active resident profile. Existing links are never deleted automatically.</p>
            @if (! $user->residentProfile && $user->residentApplication)
                <div class="mt-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm">
                    <p class="font-semibold text-sky-900">Resident application available</p>
                    <p class="mt-1 text-sky-800">{{ $user->residentApplication->first_name }} {{ $user->residentApplication->last_name }} · {{ optional($user->residentApplication->birth_date)->format('M j, Y') }}</p>
                    <form method="POST" action="{{ route('admin.users.resident-profile-from-application', $user) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-primary">Create profile from application</button>
                    </form>
                </div>
            @endif
            <form method="POST" action="{{ route('admin.users.resident-link', $user) }}" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end">
                @csrf
                @method('PATCH')
                <div class="min-w-0 flex-1">
                    <label for="resident-link" class="mb-1 block text-sm font-medium text-slate-700">Resident profile</label>
                    <select id="resident-link" name="resident_id" required class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                        <option value="">Choose an active resident…</option>
                        @foreach ($availableResidents as $available)
                            <option value="{{ $available->id }}" @selected((string) old('resident_id', $user->residentProfile?->id) === (string) $available->id)>
                                {{ $available->full_name }}{{ $available->user_id ? ' — currently linked' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('resident_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary">Save resident link</button>
            </form>
            @if ($user->residentProfile)
                <p class="mt-3 text-sm text-slate-500">
                    Currently linked to <span class="font-medium text-slate-700">{{ $user->residentProfile->full_name }}</span>.
                    <a href="{{ route('residents.index', ['search' => $user->residentProfile->full_name]) }}" class="font-medium text-sky-700 hover:underline">View in the resident directory →</a>
                </p>
            @endif
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Edit identity</h2>
            <p class="mt-1 text-sm text-slate-500">Only the name and email are editable here. Role and access actions are explicit below.</p>
            <div class="mt-5">
                @include('admin.users._form', ['user' => $user])
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Access actions</h2>
            <p class="mt-1 text-sm text-slate-500">All access changes are recorded in the audit log.</p>

            <div class="mt-5 border-t border-slate-200 pt-5">
                <h3 class="text-sm font-semibold text-slate-900">Role</h3>
                @if ($user->id === auth()->id())
                    <p class="mt-2 text-sm text-slate-500">You cannot change your own role.</p>
                @elseif (! $user->isActive())
                    <p class="mt-2 text-sm text-slate-500">Only active accounts can change roles.</p>
                @else
                    <form method="POST" action="{{ route('admin.users.role', $user) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
                        @csrf
                        @method('PATCH')
                        <label for="account-role" class="sr-only">Account role</label>
                        <select id="account-role" name="user_type" class="min-h-11 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-600">
                            <option value="admin" @selected($user->user_type === 'admin')>Administrator</option>
                            <option value="staff" @selected($user->user_type === 'staff')>Staff</option>
                            <option value="official" @selected($user->user_type === 'official')>Official</option>
                            <option value="resident" @selected($user->user_type === 'resident')>Resident</option>
                        </select>
                        <button type="submit" class="btn btn-outline-success">Update role</button>
                    </form>
                @endif
            </div>

            <div class="mt-5 border-t border-slate-200 pt-5">
                <h3 class="text-sm font-semibold text-slate-900">Suspension</h3>
                @if ($user->isSuspended())
                    <p class="mt-2 text-sm text-slate-500">This account is suspended and cannot sign in or request a reset code.</p>
                    <form method="POST" action="{{ route('admin.users.reactivate', $user) }}" class="mt-3" data-confirm="Reactivate this account?" data-confirm-title="Reactivate account" data-confirm-accept="Reactivate" data-confirm-icon="check-circle" data-confirm-tone="primary">
                        @csrf
                        <button type="submit" class="btn btn-primary">Reactivate account</button>
                    </form>
                @elseif ($user->id === auth()->id())
                    <p class="mt-2 text-sm text-slate-500">You cannot suspend your own account.</p>
                @elseif (! $user->isActive())
                    <p class="mt-2 text-sm text-slate-500">Only active accounts can be suspended.</p>
                @else
                    <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="mt-3 space-y-2" data-confirm="Suspend this account and revoke its sessions?" data-confirm-title="Suspend account" data-confirm-accept="Suspend" data-confirm-icon="x-circle">
                        @csrf
                        <label for="suspension-reason" class="block text-sm font-medium text-slate-700">Reason</label>
                        <textarea id="suspension-reason" name="reason" rows="2" required minlength="5" maxlength="500" placeholder="Reason sent to the account holder" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500">{{ old('reason') }}</textarea>
                        @error('reason')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="btn btn-outline-danger">Suspend account</button>
                    </form>
                @endif
            </div>

            <div class="mt-5 border-t border-slate-200 pt-5">
                <h3 class="text-sm font-semibold text-slate-900">Password reset</h3>
                @if ($user->id === auth()->id())
                    <p class="mt-2 text-sm text-slate-500">Use the normal Forgot Password flow for your own account.</p>
                @elseif (! $user->isActive())
                    <p class="mt-2 text-sm text-slate-500">Reset codes are available only for active accounts.</p>
                @else
                    <p class="mt-2 text-sm text-slate-500">A one-time six-character code will be emailed to this account. The code is never shown here.</p>
                    <form method="POST" action="{{ route('admin.users.reset', $user) }}" class="mt-3" data-confirm="Send a password reset code to this account?" data-confirm-title="Send reset code" data-confirm-accept="Send code" data-confirm-icon="envelope" data-confirm-tone="primary">
                        @csrf
                        <button type="submit" class="btn btn-outline">Send reset code</button>
                    </form>
                @endif
            </div>

            <div class="mt-5 border-t border-slate-200 pt-5">
                <h3 class="text-sm font-semibold text-slate-900">Email sign-in codes (lost-card bridge)</h3>
                @if (! $user->isOfficeUser())
                    <p class="mt-2 text-sm text-slate-500">Resident accounts never use two-factor sign-in, so email sign-in codes do not apply.</p>
                @else
                    <p class="mt-2 text-sm text-slate-500">
                        Currently: <span class="font-semibold {{ $user->email_otp_fallback ? 'text-sky-700' : 'text-slate-700' }}">{{ $user->email_otp_fallback ? 'enabled' : 'disabled' }}</span>.
                        When enabled, the login challenge emails this account a one-time sign-in code instead of asking for an authenticator or login-card code. Every change is recorded in the audit log.
                    </p>
                    <form method="POST" action="{{ route('admin.users.email-otp-fallback', $user) }}" class="mt-3"
                        data-confirm="{{ $user->email_otp_fallback ? 'Disable email sign-in codes for this account?' : 'Enable email sign-in codes for this account? The login challenge will email a code instead of asking for an authenticator or card code.' }}"
                        data-confirm-title="Email sign-in codes"
                        data-confirm-accept="{{ $user->email_otp_fallback ? 'Disable' : 'Enable' }}"
                        data-confirm-icon="envelope" data-confirm-tone="primary">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="email_otp_fallback" value="{{ $user->email_otp_fallback ? '0' : '1' }}">
                        <button type="submit" class="btn btn-outline">{{ $user->email_otp_fallback ? 'Disable email sign-in codes' : 'Enable email sign-in codes' }}</button>
                    </form>
                @endif
            </div>
        </section>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Account audit history</h2>
            <p class="mt-1 text-sm text-slate-500">The 50 most recent events recorded for this account.</p>
        </div>
        @if ($auditLogs->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-slate-500">No audit events are recorded for this account yet.</p>
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <caption class="sr-only">Audit history for {{ $user->email }}</caption>
                    <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Event</th>
                            <th scope="col" class="px-4 py-3 font-medium">Actor</th>
                            <th scope="col" class="hidden px-4 py-3 font-medium print:table-cell md:table-cell">When</th>
                            <th scope="col" class="px-4 py-3 font-medium">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($auditLogs as $entry)
                            @php
                                $properties = $entry->properties ?? [];
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $entry->event_label }}
                                    <span class="mt-1 block text-xs font-normal text-slate-500 md:hidden">{{ $entry->occurred_at?->format('M j, Y g:i A') ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $entry->actor_email ?? data_get($properties, 'actor_email') ?? (($entry->actor_type ?? 'guest') === 'guest' ? 'Guest' : 'System') }}</td>
                                <td class="hidden whitespace-nowrap px-4 py-3 text-slate-600 print:table-cell md:table-cell">{{ $entry->occurred_at?->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">
                                    @if (! empty($properties['changed_fields']))
                                        Updated: {{ implode(', ', $properties['changed_fields']) }}
                                    @elseif (! empty($properties['from_role']) || ! empty($properties['to_role']))
                                        {{ $properties['from_role'] ?? '—' }} → {{ $properties['to_role'] ?? '—' }}
                                    @elseif (! empty($properties['reason']))
                                        {{ $properties['reason'] }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
</x-app-layout>
