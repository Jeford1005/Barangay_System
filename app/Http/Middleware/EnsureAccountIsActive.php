<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Appended to the web group: anyone signed in who is not "active" sees a
 * status page instead of any application screen, but may always sign out.
 *
 * Registered accounts (pending), rejected and suspended accounts are all
 * handled here so no controller ever has to re-check.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isActive()) {
            return $next($request);
        }

        // Signing out is always allowed, whatever the status.
        if ($request->route()?->getName() === 'logout') {
            return $next($request);
        }

        return response()->view('auth.account-status', [
            'user' => $user,
            'title' => match ($user->status) {
                User::STATUS_PENDING => 'Account awaiting approval',
                User::STATUS_REJECTED => 'Account not approved',
                User::STATUS_SUSPENDED => 'Account suspended',
                default => 'Account restricted',
            },
            'message' => match ($user->status) {
                User::STATUS_PENDING => 'Your account has been created and is now waiting for the barangay administrator to approve it. Please try again later.',
                User::STATUS_REJECTED => 'Your account was not approved. Please visit the barangay office for assistance.',
                User::STATUS_SUSPENDED => 'Your account has been suspended'.($user->suspension_reason ? ': '.$user->suspension_reason : '. Please contact the barangay office.'),
                default => 'Your account cannot access the system right now.',
            },
        ], 403);
    }
}
