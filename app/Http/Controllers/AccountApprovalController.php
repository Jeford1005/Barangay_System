<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use App\Notifications\AccountRejectedNotification;
use Illuminate\Http\Request;
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
     */
    public function approve(Request $request, User $user)
    {
        $this->authorizePending($request, $user);

        $user->update([
            'status' => 'approved',
            'approved_at' => now(),
            'reviewed_by' => $request->user()->id,
            'rejection_reason' => null,
        ]);

        $user->residentApplication?->update([
            'status' => 'Approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $user->notify(new AccountApprovedNotification($user->name));

        AuditLog::recordWithSubject(
            'account.approved',
            $request->user()?->id,
            $request->user()?->email,
            $request->ip(),
            $request->userAgent(),
            'user',
            $user->id,
            $user->email,
        );

        return back()->with('success', "Approved — {$user->name} can sign in after staff links or creates the resident profile.");
    }

    /**
     * Reject a pending account with a reason.
     */
    public function reject(Request $request, User $user)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'A reason is required — it is sent to the applicant by email.',
        ]);

        $this->authorizePending($request, $user);

        $user->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'rejection_reason' => $validated['reason'],
        ]);

        $user->residentApplication?->update([
            'status' => 'Rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['reason'],
        ]);

        $user->notify(new AccountRejectedNotification($user->name, $validated['reason']));

        AuditLog::recordWithSubject(
            'account.rejected',
            $request->user()?->id,
            $request->user()?->email,
            $request->ip(),
            $request->userAgent(),
            'user',
            $user->id,
            $user->email,
        );

        return back()->with('success', "Application of {$user->name} was rejected and the applicant was notified.");
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
