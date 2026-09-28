<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsResident;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Vercel terminates TLS at its edge and forwards the request over plain
        // HTTP with X-Forwarded-* headers. Without trusting that proxy Laravel
        // sees http:// and generates http:// asset, form-action and redirect
        // URLs, which browsers block as mixed content on an https:// page.
        //
        // Opt-in only: nothing is trusted by default, and `env()` is read
        // directly because the config repository is not yet bound this early in
        // bootstrap. Vercel injects real process environment variables, so this
        // still resolves when the config cache is in place.
        if (env('APP_TRUSTED_PROXIES') === 'vercel') {
            $middleware->trustProxies(at: '*');
        }

        $middleware->web(append: [
            EnsureUserIsActive::class,
        ]);

        $middleware->append(AddSecurityHeaders::class);

        $middleware->alias([
            // Role and permission gates based on users.user_type.
            'admin' => EnsureUserIsAdmin::class,
            'staff' => EnsureUserHasPermission::class,
            'permission' => EnsureUserHasPermission::class,
            'resident' => EnsureUserIsResident::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
