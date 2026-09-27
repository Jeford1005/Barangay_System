<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware: ->middleware('admin') — administrator screens only. */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->isAdmin()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'You do not have permission to open that page.');
        }

        return $next($request);
    }
}
