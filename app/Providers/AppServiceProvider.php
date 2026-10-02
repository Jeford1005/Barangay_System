<?php

namespace App\Providers;

use App\Models\CertificateRequest;
use App\Models\ResidentRecordChange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
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

        // Eager by default, lazy by exception — enforced, not just advised.
        // Rule of thumb: inside a @foreach or a sidebar badge, eager
        // (with()/withCount()); everywhere else, lazy. Outside production
        // any lazy load throws, so N+1s fail verification here instead of
        // reaching prod. Production stays fail-open (violations only log).
        Model::preventLazyLoading(! $this->app->isProduction());

        // Module-tab badges (certificate requests / resident corrections) are
        // rendered on every folded-module page. Compute them once per request
        // and cache briefly; reuse the sidebar badge payload when it is
        // already available so the two strips never recount the same queues.
        // The Blade itself is untouched — the cached payload is shared with
        // the component for any consumer.
        View::composer('components.module-tabs', function ($view): void {
            if (! app()->bound('module-tabs.badges')) {
                $badges = null;

                if (app()->bound('sidebar.badges')) {
                    $sidebar = app('sidebar.badges');
                    $badges = [
                        'certRequests' => (int) ($sidebar['certRequests'] ?? 0),
                        'residentChanges' => (int) ($sidebar['residentChanges'] ?? 0),
                    ];
                }

                if ($badges === null && ($userId = auth()->id())) {
                    $sidebarCached = Cache::get('sidebar.badges.'.$userId);
                    if (is_array($sidebarCached)
                        && array_key_exists('certRequests', $sidebarCached)
                        && array_key_exists('residentChanges', $sidebarCached)) {
                        $badges = [
                            'certRequests' => (int) $sidebarCached['certRequests'],
                            'residentChanges' => (int) $sidebarCached['residentChanges'],
                        ];
                    }
                }

                if ($badges === null) {
                    $badges = Cache::remember('module-tabs.badges', now()->addSeconds(30), fn () => [
                        'certRequests' => CertificateRequest::pending()->count(),
                        'residentChanges' => ResidentRecordChange::where('status', 'Pending')->count(),
                    ]);
                }

                app()->instance('module-tabs.badges', $badges);
            }

            $view->with('moduleTabBadges', app('module-tabs.badges'));
        });
    }
}
