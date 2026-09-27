<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware: ->middleware('resident') — self-service portal only. */
class EnsureUserIsResident
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->isResident()) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'That page is for resident accounts only.');
        }

        return $next($request);
    }
}
