<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Make back() work with the pathless address bar.
 *
 * components/bare-url rewrites every page's address bar to the bare
 * domain, so browsers send `Referer: https://host/` on every form POST —
 * and UrlGenerator::previous() prefers Referer over the session's real
 * previous URL. Without this middleware every back() lands on `/`,
 * which re-routes to the dashboard/portal and silently eats the flashed
 * toast on the middle hop. Dropping the bare same-host Referer lets
 * back() fall back to the session's real previous page instead.
 * Real paths and cross-origin Referers are never touched.
 */
class RestoreBackUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $referer = $request->headers->get('referer');

        if (is_string($referer) && $this->isBareSameHost($request, $referer)) {
            $request->headers->remove('referer');
        }

        return $next($request);
    }

    private function isBareSameHost(Request $request, string $referer): bool
    {
        $parts = parse_url($referer);

        if (! is_array($parts)) {
            return false;
        }

        if (rtrim($parts['path'] ?? '/', '/') !== '') {
            return false;
        }

        $refererHost = strtolower($parts['host'] ?? '');

        return $refererHost === '' || $refererHost === strtolower($request->getHost());
    }
}
