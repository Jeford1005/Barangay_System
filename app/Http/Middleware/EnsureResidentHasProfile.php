<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Friendly gate for portal pages when the account has no linked resident
 * record yet (approved but staff haven't linked/created it). GET pages
 * render an explanatory screen instead of a bare 404; form submissions
 * keep their controller aborts because there is no page to show.
 */
class EnsureResidentHasProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('get') && ! $request->user()?->residentProfile) {
            return response()->view('resident.no-profile');
        }

        return $next($request);
    }
}
