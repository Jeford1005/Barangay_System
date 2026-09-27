<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountRequest;
use App\Models\AuditLog;
use App\Models\Resident;
use App\Models\ResidentApplication;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Account management: administrators create staff/official/resident accounts
 * and review resident self-registrations. Approving an applicant also converts
 * their submitted ResidentApplication into a resident profile (with a guard
 * against duplicate profiles), mirroring the barangay office's paperwork.
 */
class AccountController extends Controller
{
    public function index(Request $request): mixed
    {
        $users = User::query()
            ->with(['residentApplication.purok', 'residentApplication.household', 'residentApplication.reviewer'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'active' THEN 1 WHEN 'suspended' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.accounts', [
            'users' => $users,
            'counts' => [
                'pending' => User::where('status', User::STATUS_PENDING)->count(),
                'active' => User::where('status', User::STATUS_ACTIVE)->count(),
                'suspended' => User::where('status', User::STATUS_SUSPENDED)->count(),
                'rejected' => User::where('status', User::STATUS_REJECTED)->count(),
            ],
        ]);
    }

    /** Create a staff / official / resident account on behalf of the barangay. */
    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->string('name')->trim()->toString(),
            'email' => strtolower($request->string('email')->trim()->toString()),
            'password' => $request->string('password')->toString(),
        ]);

        $admin = $request->user();
        $user->forceFill([
            'role' => $request->input('role', User::ROLE_STAFF),
            'status' => User::STATUS_ACTIVE,
            'approved_at' => now(),
            'reviewed_by' => $admin->id,
        ])->save();

        AuditLog::record('created', 'user', $user->id, null, [
            'email' => $user->email,
            'role' => $user->role,
            'source' => 'admin-created',
        ]);

        return redirect()
            ->route('admin.accounts.index')
            ->with('status', "Account for {$user->name} created and activated.");
    }

    /**
     * Approve a pending account. When the applicant submitted a resident
     * application, the profile is created (or matched by email) in the same
     * transaction so the portal works the moment the resident signs in.
     */
    public function approve(Request $request, User $user): RedirectResponse
    {
        if (! $user->isPending()) {
            return $this->rejectAction('Only accounts awaiting approval can be approved.');
        }

        $note = null;

        DB::transaction(function () use ($request, $user, &$note): void {
            $user->forceFill([
                'status' => User::STATUS_ACTIVE,
                'approved_at' => now(),
                'reviewed_by' => $request->user()->id,
                'rejection_reason' => null,
            ])->save();

            $note = $this->applyApplicationProfile($request->user(), $user);
        });

        AuditLog::record('approved', 'user', $user->id, null, [
            'email' => $user->email,
            'profile' => $note ?? 'unchanged',
        ]);

        return back()->with('status', trim("Account for {$user->name} approved. {$note}"));
    }

    /** Reject a pending account, optionally with a reason shown to the applicant. */
    public function reject(Request $request, User $user): RedirectResponse
    {
        if (! $user->isPending()) {
            return $this->rejectAction('Only accounts awaiting approval can be rejected.');
        }

        $reason = trim((string) $request->input('reason', ''));
        if ($reason !== '' && mb_strlen($reason) < 5) {
            return $this->rejectAction('The reason must be at least 5 characters.');
        }
        if (mb_strlen($reason) > 500) {
            return $this->rejectAction('The reason may not exceed 500 characters.');
        }
        $reason = $reason !== '' ? $reason : null;

        DB::transaction(function () use ($request, $user, $reason): void {
            $user->forceFill([
                'status' => User::STATUS_REJECTED,
                'reviewed_by' => $request->user()->id,
                'rejection_reason' => $reason,
            ])->save();

            $application = $this->lockedApplication($user);
            if ($application !== null && $application->isPending()) {
                $application->forceFill([
                    'status' => ResidentApplication::STATUS_REJECTED,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'review_note' => $reason,
                ])->save();
            }
        });

        AuditLog::record('rejected', 'user', $user->id, null, [
            'email' => $user->email,
            'reason' => $reason,
        ]);

        return back()->with('status', "Account for {$user->name} rejected.");
    }

    /**
     * Convert an approved applicant's submitted details into a resident
     * profile (standalone action — also runs automatically on approval).
     */
    public function createResidentFromApplication(Request $request, User $user): RedirectResponse
    {
        if ($user->role !== User::ROLE_RESIDENT) {
            return $this->rejectAction('Only resident accounts can have a profile created from an application.');
        }

        if ($user->resident_id !== null && Resident::whereKey($user->resident_id)->exists()) {
            return $this->rejectAction('This account already has a resident profile.');
        }

        $application = $user->residentApplication;
        if ($application === null) {
            return $this->rejectAction('No resident application is available for this account.');
        }
        if (in_array($application->status, [ResidentApplication::STATUS_REJECTED, ResidentApplication::STATUS_CANCELLED], true)) {
            return $this->rejectAction('This resident application is no longer active.');
        }

        try {
            $note = null;
            DB::transaction(function () use ($request, $user, &$note): void {
                $note = $this->applyApplicationProfile($request->user(), $user);
            });
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('status', $note ?? 'Resident profile already exists for this account.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->rejectAction('You cannot suspend your own account.');
        }

        if (! $user->isActive()) {
            return $this->rejectAction('Only active accounts can be suspended.');
        }

        $reason = trim($request->input('reason', ''));
        $user->forceFill([
            'status' => User::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspension_reason' => $reason !== '' ? $reason : null,
            'reviewed_by' => $request->user()->id,
        ])->save();

        AuditLog::record('suspended', 'user', $user->id, null, ['reason' => $reason ?: null]);

        return back()->with('status', "Account for {$user->name} suspended.");
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        if (! in_array($user->status, [User::STATUS_SUSPENDED, User::STATUS_REJECTED], true)) {
            return $this->rejectAction('Only suspended or rejected accounts can be reactivated.');
        }

        $user->forceFill([
            'status' => User::STATUS_ACTIVE,
            'approved_at' => $user->approved_at ?? now(),
            'reviewed_by' => $request->user()->id,
            'suspended_at' => null,
            'suspension_reason' => null,
            'rejection_reason' => null,
        ])->save();

        AuditLog::record('reactivated', 'user', $user->id, null, ['email' => $user->email]);

        return back()->with('status', "Account for {$user->name} reactivated.");
    }

    public function changeRole(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->rejectAction('You cannot change your own role.');
        }

        $validated = $request->validate([
            'role' => ['required', 'in:admin,staff,resident'],
        ]);

        $before = ['role' => $user->role];
        $user->forceFill(['role' => $validated['role']])->save();

        AuditLog::record('role_changed', 'user', $user->id, $before, ['role' => $user->role]);

        return back()->with('status', "{$user->name} is now {$user->role}.");
    }

    /* ------------------------------------------------------------------ */

    /**
     * Link or create the resident profile described by the account's pending
     * application. Runs inside the caller's transaction; returns a note for
     * the flash message (null when nothing needed doing).
     */
    private function applyApplicationProfile(User $admin, User $user): ?string
    {
        if ($user->role !== User::ROLE_RESIDENT) {
            return null;
        }

        if ($user->resident_id !== null && Resident::whereKey($user->resident_id)->exists()) {
            return null;
        }

        $application = $this->lockedApplication($user);
        if ($application === null
            || in_array($application->status, [ResidentApplication::STATUS_REJECTED, ResidentApplication::STATUS_CANCELLED], true)) {
            return null;
        }

        // Never create a duplicate: an office-entered resident with the same
        // email is the same person — link to it instead.
        $resident = Resident::where('email', $user->email)->first();

        if ($resident === null) {
            $resident = Resident::create($application->toResidentAttributes() + [
                'status' => Resident::STATUS_ACTIVE,
            ]);

            $note = 'Resident profile created from the application.';
        } else {
            $note = "Linked to the existing resident record for {$resident->full_name}.";
        }

        $user->forceFill(['resident_id' => $resident->id])->save();

        if ($application->isPending()) {
            $application->forceFill([
                'status' => ResidentApplication::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();
        }

        AuditLog::record('profile_created', 'resident', $resident->id, null, [
            'user_id' => $user->id,
            'application_id' => $application->id,
        ]);

        return $note;
    }

    private function lockedApplication(User $user): ?ResidentApplication
    {
        return ResidentApplication::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();
    }

    private function rejectAction(string $message): RedirectResponse
    {
        return back()->with('error', $message);
    }
}
