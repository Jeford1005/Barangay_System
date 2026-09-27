<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Account management: administrators create staff/official/resident accounts,
 * approve self-registered residents, and suspend or re-role anyone.
 */
class AccountController extends Controller
{
    public function index(Request $request): mixed
    {
        $users = User::query()
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
            'role' => $request->validated('role'),
            'status' => User::STATUS_ACTIVE,
            'approved_at' => now(),
            'reviewed_by' => $admin->id,
        ])->save();

        return redirect()
            ->route('admin.accounts.index')
            ->with('status', "Account for {$user->name} created and activated.");
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        if (! $user->isPending()) {
            return $this->rejectAction('Only accounts awaiting approval can be approved.');
        }

        $user->forceFill([
            'status' => User::STATUS_ACTIVE,
            'approved_at' => now(),
            'reviewed_by' => $request->user()->id,
        ])->save();

        return back()->with('status', "Account for {$user->name} approved. They can now sign in.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        if (! $user->isPending()) {
            return $this->rejectAction('Only accounts awaiting approval can be rejected.');
        }

        $user->forceFill([
            'status' => User::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
        ])->save();

        return back()->with('status', "Account for {$user->name} rejected.");
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
        ])->save();

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

        $user->forceFill(['role' => $validated['role']])->save();

        return back()->with('status', "{$user->name} is now {$user->role}.");
    }

    private function rejectAction(string $message): RedirectResponse
    {
        return back()->with('error', $message);
    }
}
