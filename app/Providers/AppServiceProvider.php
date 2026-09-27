<?php

namespace App\Providers;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel 12 requires PHP 8.2+. Composer's platform check already
        // refuses to boot on older runtimes, but a clear message here names the
        // actual requirement — which matters because a stock XAMPP install
        // ships an older PHP and fails deep inside a request with no explanation.
        if (PHP_VERSION_ID < 80200) {
            throw new RuntimeException(sprintf(
                'This application requires PHP 8.2 or newer (Laravel %s). The server is running PHP %s. Point Apache/PHP-FPM at a PHP 8.2+ runtime.',
                $this->app->version(),
                PHP_VERSION,
            ));
        }

        $heartbeat = function (): void {
            Cache::put('system.queue.heartbeat', now()->timestamp, now()->addMinutes(10));
        };

        Event::listen(JobProcessed::class, $heartbeat);
        Event::listen(JobExceptionOccurred::class, $heartbeat);
    }
}
