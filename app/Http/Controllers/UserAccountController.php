<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Resident;
use App\Models\ResidentApplication;
use App\Models\User;
use App\Notifications\AccountAccessChangedNotification;
use App\Services\PasswordResetCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserAccountController extends Controller
{
    public function __construct(
        private readonly PasswordResetCodeService $resetCodes,
    ) {}

    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->input('search', '')), 0, 100);
        $role = (string) $request->input('role', '');
        $status = (string) $request->input('status', '');

        $query = User::query()->with('residentProfile');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (in_array($role, ['admin', 'staff', 'resident'], true)) {
            $query->where('user_type', $role);
        }

        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        } elseif ($status === 'suspended') {
            $query->whereNotNull('suspended_at');
        }

        $users = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $pendingCount = User::where('user_type', 'resident')->where('status', 'pending')->count();

        return view('admin.users.index', compact('users', 'pendingCount', 'search', 'role', 'status'));
    }

    public function show(User $user): View
    {
        $user->load([
            'residentProfile.purok',
            'residentProfile.household',
            'residentApplication',
            'reviewer',
            'suspender',
        ]);

        $auditLogs = AuditLog::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('actor_id', $user->id)
                    ->orWhere('subject_id', $user->id);
            })
            ->latest('occurred_at')
            ->limit(50)
            ->get();

        $availableResidents = Resident::query()
            ->where('status', 'Active')
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id')->orWhere('user_id', $user->id);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix']);

        return view('admin.users.show', compact('user', 'auditLogs', 'availableResidents'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $oldEmail = $user->email;
        $changed = [];

        if ($validated['name'] !== $user->name) {
            $changed[] = 'name';
        }
        if ($validated['email'] !== $user->email) {
            $changed[] = 'email';
        }

        $user->fill($validated)->save();

        if ($oldEmail !== $user->email) {
            DB::table('password_reset_tokens')
                ->whereIn('email', array_unique([$oldEmail, $user->email]))
                ->delete();
            $this->revokeUserSessions($user);
        }

        if ($changed !== []) {
            $this->recordAudit($request, $user, 'account.updated', [
                'changed_fields' => $changed,
            ]);
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', $changed === [] ? 'No account changes were made.' : 'Account details updated.');
    }

    public function createResidentFromApplication(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->user_type === 'resident', 422, 'Only resident accounts can create resident profiles.');

        if ($user->residentProfile) {
            return back()->withErrors(['resident_id' => 'This account already has a resident profile.']);
        }

        $application = $user->residentApplication;
        abort_if(! $application, 422, 'No resident application is available for this account.');
        abort_if($application->status === 'Rejected' || $application->status === 'Cancelled', 422, 'This resident application is no longer active.');

        try {
            DB::transaction(function () use ($request, $user, $application) {
                $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $lockedApplication = ResidentApplication::query()
                    ->whereKey($application->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedUser->residentProfile) {
                    throw ValidationException::withMessages([
                        'resident_id' => 'This account already has a resident profile.',
                    ]);
                }

                $resident = Resident::create([
                    'first_name' => $lockedApplication->first_name,
                    'middle_name' => $lockedApplication->middle_name,
                    'last_name' => $lockedApplication->last_name,
                    'suffix' => $lockedApplication->suffix,
                    'birth_date' => $lockedApplication->birth_date,
                    'sex' => $lockedApplication->sex,
                    'civil_status' => $lockedApplication->civil_status,
                    'phone_number' => $lockedApplication->phone_number,
                    'email' => $lockedApplication->email,
                    'address' => $lockedApplication->address,
                    'purok_id' => $lockedApplication->purok_id,
                    'household_id' => $lockedApplication->household_id,
                    'nationality' => 'Filipino',
                    'status' => 'Active',
                    'user_id' => $lockedUser->id,
                    'created_by' => $request->user()->id,
                ]);

                $lockedApplication->update([
                    'status' => 'Approved',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);

                AuditLog::recordWithSubject(
                    'resident.profile_created_from_application',
                    $request->user()?->id,
                    $request->user()?->email,
                    $request->ip(),
                    $request->userAgent(),
                    'resident',
                    $resident->id,
                    $resident->full_name,
                    ['user_id' => $lockedUser->id, 'application_id' => $lockedApplication->id],
                );
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Resident profile created from the application.');
    }

    public function unlinkResident(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->user_type === 'resident', 422, 'Only resident accounts can unlink resident profiles.');

        DB::transaction(function () use ($request, $user) {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $resident = $lockedUser->residentProfile;
            abort_if(! $resident, 422, 'This account has no resident profile to unlink.');

            $resident->update(['user_id' => null, 'updated_at' => now()]);
            $lockedUser->update([
                'suspended_at' => now(),
                'suspended_by' => $request->user()->id,
                'suspension_reason' => 'Resident account link removed by staff.',
            ]);
            $this->revokeUserSessions($lockedUser);

            AuditLog::recordWithSubject(
                'account.resident_unlinked',
                $request->user()?->id,
                $request->user()?->email,
                $request->ip(),
                $request->userAgent(),
                'resident',
                $resident->id,
                $resident->full_name,
                ['user_id' => $lockedUser->id],
            );
        });

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Resident profile unlinked and the account suspended.');
    }

    public function linkResident(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->user_type === 'resident', 422, 'Only resident accounts can link resident profiles.');

        $validated = $request->validate([
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
        ], [
            'resident_id.required' => 'Choose a resident profile to link.',
            'resident_id.exists' => 'The selected resident profile is no longer available.',
        ]);

        try {
            DB::transaction(function () use ($request, $user, $validated) {
                $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $resident = Resident::query()->whereKey($validated['resident_id'])->lockForUpdate()->firstOrFail();

                if ($resident->user_id && $resident->user_id !== $lockedUser->id) {
                    throw ValidationException::withMessages([
                        'resident_id' => 'That resident profile is already linked to another account.',
                    ]);
                }

                $previousResidentId = $lockedUser->residentProfile?->id;
                if ($previousResidentId && $previousResidentId !== $resident->id) {
                    throw ValidationException::withMessages([
                        'resident_id' => 'This account already has a different resident profile. Unlink it explicitly before changing the link.',
                    ]);
                }

                $resident->update(['user_id' => $lockedUser->id]);

                $this->recordAudit($request, $lockedUser, 'account.resident_linked', [
                    'from_resident_id' => $previousResidentId,
                    'to_resident_id' => $resident->id,
                ]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Resident profile linked successfully.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'user_type' => ['required', Rule::in(['admin', 'staff', 'resident'])],
        ]);

        $newRole = $validated['user_type'];

        if ($newRole === $user->user_type) {
            return redirect()->route('admin.users.show', $user)
                ->with('status', 'That account already has the selected role.');
        }

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user_type' => 'You cannot change your own role.']);
        }

        if (! $user->isActive()) {
            return back()->withErrors(['user_type' => 'Only active accounts can change roles.']);
        }

        if ($newRole !== 'resident' && $user->residentProfile()->exists()) {
            return back()->withErrors([
                'user_type' => 'Unlink the resident profile before assigning an office role.',
            ]);
        }

        if ($user->isAdmin() && $newRole !== 'admin' && $this->activeAdminCount() <= 1) {
            return back()->withErrors(['user_type' => 'The last active administrator cannot be demoted.']);
        }

        if ($user->isOfficeUser() && $newRole === 'resident') {
            if (! $user->residentProfile()->exists()) {
                return back()->withErrors([
                    'user_type' => 'Link a resident profile before changing this account to resident.',
                ]);
            }
        }

        $oldRole = $user->user_type;
        $user->user_type = $newRole;
        $user->save();
        $this->revokeUserSessions($user);

        $this->recordAudit($request, $user, 'account.role_changed', [
            'from_role' => $oldRole,
            'to_role' => $newRole,
        ]);
        $this->notifySafely($user->fresh(), 'role_changed', $oldRole, $newRole);

        return redirect()->route('admin.users.show', $user)
            ->with('success', "Role changed to {$newRole}.");
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['reason' => 'You cannot suspend your own account.']);
        }

        if (! $user->isActive()) {
            return back()->withErrors(['reason' => 'Only active accounts can be suspended.']);
        }

        try {
            DB::transaction(function () use ($request, $user, $validated) {
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                if (! $locked->isActive()) {
                    throw new \RuntimeException('Only active accounts can be suspended.');
                }

                if ($locked->user_type === 'admin' && $this->activeAdminCount() <= 1) {
                    throw new \RuntimeException('The last active administrator cannot be suspended.');
                }

                $locked->suspended_at = now();
                $locked->suspended_by = $request->user()->id;
                $locked->suspension_reason = $validated['reason'];
                $locked->save();

                DB::table('password_reset_tokens')->where('email', $locked->email)->delete();
                $this->revokeUserSessions($locked);

                $this->recordAudit($request, $locked, 'account.suspended', [
                    'reason' => $validated['reason'],
                ]);
            });
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['reason' => $exception->getMessage()]);
        }

        $this->notifySafely($user->fresh(), 'suspended', reason: $validated['reason']);

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Account suspended and existing sessions revoked.');
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if (! $user->isSuspended() || ! $user->isApproved()) {
            return back()->withErrors(['user' => 'Only suspended approved accounts can be reactivated.']);
        }

        DB::transaction(function () use ($request, $user) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->suspended_at = null;
            $locked->suspended_by = null;
            $locked->suspension_reason = null;
            $locked->save();

            $this->recordAudit($request, $locked, 'account.reactivated');
        });

        $this->notifySafely($user->fresh(), 'reactivated');

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Account reactivated.');
    }

    public function sendResetCode(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Use the normal Forgot Password flow for your own account.']);
        }

        if (! $user->isActive()) {
            return back()->withErrors(['user' => 'Reset codes can only be sent to active accounts.']);
        }

        try {
            $this->resetCodes->issue($user);
        } catch (\Throwable $exception) {
            report($exception);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            return back()->withErrors([
                'user' => 'The reset email could not be sent. Check the mail configuration and try again.',
            ]);
        }

        $this->revokeUserSessions($user);
        $this->recordAudit($request, $user, 'account.password_reset_initiated');

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'A reset code was sent to the account email.');
    }

    private function activeAdminCount(): int
    {
        return User::where('user_type', 'admin')
            ->where('status', 'approved')
            ->whereNull('suspended_at')
            ->count();
    }

    private function revokeUserSessions(User $user): void
    {
        $user->forceFill(['remember_token' => null])->saveQuietly();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }

    /**
     * Account audit events use the target as the top-level subject, matching
     * the existing account-approval and password-reset events. The acting
     * administrator is retained in properties for accountability.
     */
    private function recordAudit(Request $request, User $target, string $event, array $properties = []): void
    {
        $actor = $request->user();

        AuditLog::recordWithSubject(
            $event,
            $actor?->id,
            $actor?->email,
            $request->ip(),
            $request->userAgent(),
            'user',
            $target->id,
            $target->email,
            array_merge(['source' => 'admin'], $properties),
        );
    }

    private function notifySafely(
        User $user,
        string $change,
        ?string $fromRole = null,
        ?string $toRole = null,
        ?string $reason = null,
    ): void {
        try {
            $user->notify(new AccountAccessChangedNotification(
                userName: $user->name,
                change: $change,
                fromRole: $fromRole,
                toRole: $toRole,
                reason: $reason,
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
