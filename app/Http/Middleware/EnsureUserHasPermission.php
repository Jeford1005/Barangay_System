<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Allow an authenticated user with the requested application permission.
     * The default permission is used by the broad `staff` route gate.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission = 'operations.access'): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasPermission($permission)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'You do not have access to this resource.'], 403);
        }

        // Redirecting with no message is indistinguishable from a normal
        // navigation, so the user never learns the action is restricted.
        return redirect()
            ->route('dashboard')
            ->with('error', 'Your role does not have permission to open that page. Ask an administrator if you need access.');
    }
}
