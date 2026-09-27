<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: ->middleware('permission:residents.view') (comma-separated
 * for alternatives: 'permission:reports.view,analytics.view').
 *
 * Backed by User::hasPermission() — the single source of truth for what each
 * role may do. Denied users are redirected to their dashboard with a notice.
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return redirect()
            ->route('dashboard')
            ->with('error', 'You do not have permission to open that page.');
    }
}
