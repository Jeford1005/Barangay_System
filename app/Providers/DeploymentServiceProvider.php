<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Deployment-environment glue.
 *
 * Two things differ between local XAMPP and a container host that terminates
 * TLS in front of PHP: the request scheme Laravel sees, and whether the app
 * can know its own public URL. Both are handled here and both are inert when
 * the matching environment variable is absent, so local development is
 * unaffected.
 */
class DeploymentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Trusted proxy handling lives in bootstrap/app.php (Laravel 12 reads
        // `trusted_proxies` from config/app.php there) -- see that file for why
        // the proxy address itself is not trusted.

        $appUrl = $this->publicUrl();

        if ($appUrl === null) {
            return;
        }

        // Behind a TLS-terminating proxy Laravel may still resolve the request
        // as http://. Forcing the root URL keeps redirects, form actions and
        // queued-notification links on https even if the forwarded headers are
        // missing on an internal request.
        URL::forceRootUrl($appUrl);

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }
    }

    private function publicUrl(): ?string
    {
        $url = env('APP_URL');

        if (! is_string($url) || $url === '' || str_contains($url, 'localhost')) {
            return null;
        }

        return rtrim($url, '/');
    }
}