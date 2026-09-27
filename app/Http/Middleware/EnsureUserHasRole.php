<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: ->middleware('role:admin') or 'role:admin,staff'.
 *
 * Non-members get a friendly redirect back to their dashboard with a notice,
 * instead of a bare 403 page.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! in_array($user->role, $roles, true)) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'You do not have permission to open that page.');
        }

        return $next($request);
    }
}
