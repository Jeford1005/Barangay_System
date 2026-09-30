<?php

namespace App\Console\Commands;

use App\Services\SystemHealthService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SystemHealthCommand extends Command
{
    protected $signature = 'system:health {--json : Output machine-readable JSON}';

    protected $description = 'Check database, cache, storage, queue, migration, and backup readiness';

    private const QUEUE_BACKLOG_WARN_THRESHOLD = 100;

    public function handle(SystemHealthService $health): int
    {
        $status = $health->check();

        if ($this->option('json')) {
            $this->line((string) json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->exitCode($status);
        }

        // Passing checks stay to one line each; anything beyond this summary
        // is printed only when something actually needs attention.
        $this->line('System health');
        $this->line('Database: '.($status['database_connected'] ? 'OK' : 'FAILED'));
        $this->line('Cache: '.($status['cache_connected'] ? 'OK' : 'FAILED'));
        $this->line('Storage writable: '.($status['storage_writable'] ? 'YES' : 'NO'));
        $this->line('Pending migrations: '.(is_countable($status['pending_migrations']) ? count($status['pending_migrations']) : 'unknown'));
        $this->line('Queued jobs: '.($status['queued_jobs'] ?? 0));
        $this->line('Failed jobs: '.($status['failed_jobs'] ?? 'unknown'));

        $this->reportQueueBacklog($status);

        foreach ($this->failureReasons($status) as $reason) {
            $this->error($reason);
        }

        return $this->exitCode($status);
    }

    /**
     * Surface the queue backlog without nagging on transient counts: the
     * waiting-job age is shown whenever work is queued, while warnings fire
     * only once the backlog actually needs an operator.
     */
    private function reportQueueBacklog(array $status): void
    {
        $queuedJobs = (int) ($status['queued_jobs'] ?? 0);

        if ($queuedJobs <= 0) {
            return;
        }

        $this->line('Oldest queued job: '.$this->describeOldestQueuedJob($status['oldest_queued_job'] ?? null));

        if ($queuedJobs < self::QUEUE_BACKLOG_WARN_THRESHOLD) {
            return;
        }

        $this->warn("Queue backlog: {$queuedJobs} jobs waiting (threshold ".self::QUEUE_BACKLOG_WARN_THRESHOLD.'). Run a queue worker or scale workers to drain it.');

        if (! $this->hasRecentWorkerHeartbeat($status['queue_heartbeat_at'] ?? null)) {
            $this->warn('Queue jobs are waiting but no worker heartbeat was seen recently — a queue worker may not be running.');
        }
    }

    private function describeOldestQueuedJob(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'unknown';
        }

        try {
            $at = Carbon::parse((string) $value);

            return $at->toDateTimeString().' ('.$at->diffForHumans().')';
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function hasRecentWorkerHeartbeat(mixed $value): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        try {
            return Carbon::createFromTimestamp((int) $value)->greaterThan(now()->subMinutes(15));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function failureReasons(array $status): array
    {
        $reasons = [];

        if (! $status['database_connected']) {
            $detail = isset($status['database_message']) && is_string($status['database_message']) && $status['database_message'] !== ''
                ? ' ('.$status['database_message'].')'
                : '';
            $reasons[] = 'Database connection FAILED'.$detail;
        }

        if (! $status['cache_connected']) {
            $reasons[] = 'Cache connection FAILED';
        }

        if (! $status['storage_writable']) {
            $reasons[] = 'Storage path is NOT writable';
        }

        if (is_countable($status['pending_migrations']) && count($status['pending_migrations']) > 0) {
            $reasons[] = 'Pending migrations: '.count($status['pending_migrations']);
        }

        if (($status['failed_jobs'] ?? 0) > 0) {
            $reasons[] = 'Failed jobs need attention: '.($status['failed_jobs'] ?? 0);
        }

        return $reasons;
    }

    private function exitCode(array $status): int
    {
        $healthy = $status['database_connected']
            && $status['cache_connected']
            && $status['storage_writable']
            && (($status['failed_jobs'] ?? 0) === 0)
            && (! is_countable($status['pending_migrations']) || count($status['pending_migrations']) === 0);

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
