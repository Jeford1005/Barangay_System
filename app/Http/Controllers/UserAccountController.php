<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Concerns\Searchable;
use App\Models\Resident;
use App\Models\ResidentApplication;
use App\Models\User;
use App\Notifications\AccountAccessChangedNotification;
use App\Services\PasswordResetCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserAccountController extends Controller
{
    public function __construct(
        private readonly PasswordResetCodeService $resetCodes,
    ) {}

    public function index(Request $request): View
    {
        $search = Searchable::normalizeSearchTerm(mb_substr(trim((string) $request->input('search', '')), 0, 100)) ?? '';
        $role = (string) $request->input('role', '');
        $status = (string) $request->input('status', '');

        $query = User::query()->with('residentProfile');

        if ($search !== '') {
            $like = '%'.self::escapeLike($search).'%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw("name LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("email LIKE ? ESCAPE '\\'", [$like]);
            });
        }

        if (in_array($role, ['admin', 'staff', 'official', 'resident'], true)) {
            $query->where('user_type', $role);
        }

        if (in_array($status, User::ACCOUNT_STATUSES, true)) {
            $query->where('status', $status);
        } elseif ($status === User::SUSPENDED_FILTER) {
            $query->whereNotNull('suspended_at');
        }

        $users = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $pendingCount = User::where('user_type', 'resident')->where('status', 'pending')->count();

        return view('admin.users.index', compact('users', 'pendingCount', 'search', 'role', 'status'));
    }

    /**
     * Let an administrator create any account tier — including another
     * administrator — without leaving the directory. Group middleware
     * already restricts both routes to admins.
     */
    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(12)->letters()->numbers()],
            'user_type' => ['required', Rule::in(['admin', 'staff', 'official', 'resident'])],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $account = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'status' => 'approved',
            ]);

            // user_type is deliberately not fillable: privileged keys are
            // only ever assigned explicitly, never mass-assigned.
            $account->user_type = $validated['user_type'];
            $account->save();

            return $account;
        });

        $this->recordAudit($request, $user, 'account.created', [
            'user_type' => $user->user_type,
        ]);

        return redirect()->route('admin.users.show', $user)
            ->with('success', "Account for {$user->name} created with {$user->user_type} access.");
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

                $resident = new Resident([
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
                    'created_by' => $request->user()->id,
                ]);
                // The account link is privileged: set it explicitly, never
                // through mass assignment.
                $resident->user_id = $lockedUser->id;
                $resident->save();

                $lockedApplication->status = 'Approved';
                $lockedApplication->reviewed_by = $request->user()->id;
                $lockedApplication->reviewed_at = now();
                $lockedApplication->save();

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

            $resident->user_id = null;
            $resident->save();
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
            'resident_id' => ['required', 'integer', 'min:1', 'max:4294967295', 'exists:residents,id'],
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

                $resident->user_id = $lockedUser->id;
                $resident->save();

                // A (re)link means staff verified the profile, so any
                // suspension parked by a previous unlink is lifted along with
                // its metadata — otherwise the account stays suspended forever
                // despite the fresh link.
                $suspensionCleared = $lockedUser->isSuspended();
                $lockedUser->update([
                    'suspended_at' => null,
                    'suspended_by' => null,
                    'suspension_reason' => null,
                ]);

                $this->recordAudit($request, $lockedUser, 'account.resident_linked', [
                    'from_resident_id' => $previousResidentId,
                    'to_resident_id' => $resident->id,
                    'suspension_cleared' => $suspensionCleared,
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
            'user_type' => ['required', Rule::in(['admin', 'staff', 'official', 'resident'])],
        ]);

        $newRole = $validated['user_type'];

        if ($newRole === $user->user_type) {
            return redirect()->route('admin.users.show', $user)
                ->with('status', 'That account already has the selected role.');
        }

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user_type' => 'You cannot change your own role.']);
        }

        // The whole decision runs on the locked row: the pre-lock reads above
        // are UX fast-paths only. Locking every active admin row (not just the
        // target) serializes two concurrent demotions of the last two admins,
        // and the survivor count is re-checked inside the lock.
        try {
            $outcome = DB::transaction(function () use ($request, $user, $newRole) {
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                if ($newRole === $locked->user_type) {
                    return 'noop';
                }

                if (! $locked->isActive()) {
                    throw ValidationException::withMessages([
                        'user_type' => 'Only active accounts can change roles.',
                    ]);
                }

                if ($newRole !== 'resident' && $locked->residentProfile()->exists()) {
                    throw ValidationException::withMessages([
                        'user_type' => 'Unlink the resident profile before assigning an office role.',
                    ]);
                }

                if ($locked->isOfficeUser() && $newRole === 'resident' && ! $locked->residentProfile()->exists()) {
                    throw ValidationException::withMessages([
                        'user_type' => 'Link a resident profile before changing this account to resident.',
                    ]);
                }

                if ($locked->isAdmin() && $newRole !== 'admin') {
                    $remainingAdmins = User::query()
                        ->where('user_type', 'admin')
                        ->where('status', 'approved')
                        ->whereNull('suspended_at')
                        ->whereKeyNot($locked->getKey())
                        ->lockForUpdate()
                        ->pluck('id');

                    if ($remainingAdmins->isEmpty()) {
                        throw ValidationException::withMessages([
                            'user_type' => 'The last active administrator cannot be demoted.',
                        ]);
                    }
                }

                $oldRole = $locked->user_type;
                $locked->user_type = $newRole;
                $locked->save();
                $this->revokeUserSessions($locked);

                $this->recordAudit($request, $locked, 'account.role_changed', [
                    'from_role' => $oldRole,
                    'to_role' => $newRole,
                ]);

                return $oldRole;
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        if ($outcome === 'noop') {
            return redirect()->route('admin.users.show', $user)
                ->with('status', 'That account already has the selected role.');
        }

        $this->notifySafely($user->fresh(), 'role_changed', $outcome, $newRole);

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
        abort_unless($user->id !== $request->user()->id, 403, 'You cannot reactivate your own account.');

        if (! $user->isSuspended() || ! $user->isApproved()) {
            return redirect()->route('admin.users.show', $user)
                ->withErrors(['user' => 'Only suspended approved accounts can be reactivated.']);
        }

        try {
            DB::transaction(function () use ($request, $user) {
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                if (! $locked->isSuspended() || ! $locked->isApproved()) {
                    throw new \RuntimeException('Only suspended approved accounts can be reactivated.');
                }

                $locked->suspended_at = null;
                $locked->suspended_by = null;
                $locked->suspension_reason = null;
                $locked->save();

                $this->recordAudit($request, $locked, 'account.reactivated');
            });
        } catch (\RuntimeException $exception) {
            return redirect()->route('admin.users.show', $user)
                ->withErrors(['user' => $exception->getMessage()]);
        }

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

    /**
     * Escape LIKE wildcards so user input only ever matches literally.
     * To be used with an explicit `ESCAPE '\\'` clause (portable across
     * MySQL and SQLite).
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
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
