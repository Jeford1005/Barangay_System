<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser hardening applied to every response.
 *
 * The application ships no Content-Security-Policy yet: inline scripts,
 * inline styles, and `onclick` handlers are still present in the shell, so a
 * policy would need nonces first. These headers are the parts that are safe to
 * set today and that do not depend on refactoring the view layer.
 */
class AddSecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Never let a browser sniff a response into an executable type, and
        // never let a downloaded export be treated as markup.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Clickjacking the role-change and suspend forms is a realistic threat
        // for an office deployment on shared or proxied infrastructure.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Without this, resident photo URLs leak to fonts.bunny.net and other
        // third-party origins through the Referer header.
        $response->headers->set('Referrer-Policy', 'same-origin');

        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // HSTS is only meaningful (and only safe) over TLS: emitting it on
        // plain HTTP would be ignored by browsers, and unconditionally
        // setting it would pin HTTPS during local HTTP development.
        // Conservative policy: one year + subdomains, no preload directive
        // (preload is a one-way commitment owned by another workstream).
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // `no-store` keeps account and resident pages out of shared caches and
        // out of the back/forward cache after sign-out. It applies to every
        // response kind (GET, POST, JSON): a signed-in JSON payload or a
        // POST-redirect target must not sit in history or a shared cache.
        // Versioned build assets already carry an `immutable` directive from
        // the web server / Vite manifest — leave those untouched.
        $cacheControl = (string) $response->headers->get('Cache-Control', '');

        if (! str_contains($cacheControl, 'immutable')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        return $response;
    }
}
