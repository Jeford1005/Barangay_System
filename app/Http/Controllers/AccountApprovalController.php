<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ResidentApplication;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use App\Notifications\AccountRejectedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AccountApprovalController extends Controller
{
    /**
     * Show the pending account applications.
     */
    public function index(): View
    {
        $pending = User::where('status', 'pending')
            ->where('user_type', 'resident')
            ->with(['residentProfile.purok', 'residentApplication.purok', 'residentApplication.household'])
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.account-approvals', [
            'pending' => $pending,
            'recentDecisions' => User::where('user_type', 'resident')
                ->whereIn('status', ['approved', 'rejected'])
                ->whereNotNull('reviewed_by')
                ->with('reviewer')
                ->latest('approved_at')
                ->take(10)
                ->get(),
        ]);
    }

    /**
     * Approve a pending account.
     *
     * The decision runs inside a transaction with the applicant row locked,
     * so two admins (or a double-clicked button) cannot approve/reject the
     * same application twice. The audit entry is written in the same
     * transaction; the email goes out only after the commit succeeds.
     */
    public function approve(Request $request, User $user)
    {
        $this->authorizePending($request, $user);

        $actor = $request->user();
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        $decided = DB::transaction(function () use ($actor, $ip, $userAgent, $user) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                $locked->user_type === 'resident' && $locked->status === 'pending',
                404,
            );

            // The linked application has its own lifecycle (it may already
            // have been approved alongside a created profile, or rejected and
            // left stale): only a pending application may be transitioned.
            $application = ResidentApplication::query()
                ->where('user_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($application && $application->status !== 'Pending') {
                abort(422, 'This resident application is no longer pending.');
            }

            $locked->update([
                'status' => 'approved',
                'approved_at' => now(),
                'reviewed_by' => $actor->id,
                'rejection_reason' => null,
            ]);

            if ($application) {
                // Decision metadata is privileged: set it explicitly, never
                // through mass assignment.
                $application->status = 'Approved';
                $application->reviewed_by = $actor->id;
                $application->reviewed_at = now();
                $application->save();
            }

            AuditLog::recordWithSubject(
                'account.approved',
                $actor?->id,
                $actor?->email,
                $ip,
                $userAgent,
                'user',
                $locked->id,
                $locked->email,
            );

            return $locked;
        });

        try {
            $decided->notify(new AccountApprovedNotification($decided->name));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', "Approved — {$decided->name} can sign in after staff links or creates the resident profile.");
    }

    /**
     * Reject a pending account with a reason.
     *
     * Same race protection as approve: row lock, audit inside the
     * transaction, notification only after the commit.
     */
    public function reject(Request $request, User $user)
    {
        $validator = Validator::make($request->only('reason'), [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'A reason is required — it is sent to the applicant by email.',
        ]);

        if ($validator->fails()) {
            // Scope the failure to this applicant: every pending card shares
            // the `reason` field name, so without this the old input and the
            // error message would bleed into every other card on the page.
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('approval_error_user_id', $user->id);
        }

        $validated = $validator->validated();

        $this->authorizePending($request, $user);

        $actor = $request->user();
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        $decided = DB::transaction(function () use ($actor, $ip, $userAgent, $user, $validated) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                $locked->user_type === 'resident' && $locked->status === 'pending',
                404,
            );

            // Same guard as approve: a non-pending application (e.g. one
            // already approved with a created profile) must not be flipped
            // back to Rejected underneath the profile.
            $application = ResidentApplication::query()
                ->where('user_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($application && $application->status !== 'Pending') {
                abort(422, 'This resident application is no longer pending.');
            }

            $locked->update([
                'status' => 'rejected',
                'reviewed_by' => $actor->id,
                'rejection_reason' => $validated['reason'],
            ]);

            if ($application) {
                $application->status = 'Rejected';
                $application->reviewed_by = $actor->id;
                $application->reviewed_at = now();
                $application->review_note = $validated['reason'];
                $application->save();
            }

            AuditLog::recordWithSubject(
                'account.rejected',
                $actor?->id,
                $actor?->email,
                $ip,
                $userAgent,
                'user',
                $locked->id,
                $locked->email,
            );

            return $locked;
        });

        try {
            $decided->notify(new AccountRejectedNotification($decided->name, $validated['reason']));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', "Application of {$decided->name} was rejected and the applicant was notified.");
    }

    /**
     * Only pending resident accounts may be decided on.
     */
    private function authorizePending(Request $request, User $user): void
    {
        abort_unless(
            $user->user_type === 'resident' && $user->status === 'pending',
            404,
        );
    }
}
