<?php

namespace App\Console\Commands;

use App\Services\SystemHealthService;
use Illuminate\Console\Command;

class SystemHealthCommand extends Command
{
    protected $signature = 'system:health {--json : Output machine-readable JSON}';

    protected $description = 'Check database, cache, storage, queue, migration, and backup readiness';

    public function handle(SystemHealthService $health): int
    {
        $status = $health->check();

        if ($this->option('json')) {
            $this->line((string) json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->exitCode($status);
        }

        $this->line('System health');
        $this->line('Database: '.($status['database_connected'] ? 'OK' : 'FAILED'));
        $this->line('Cache: '.($status['cache_connected'] ? 'OK' : 'FAILED'));
        $this->line('Storage writable: '.($status['storage_writable'] ? 'YES' : 'NO'));
        $this->line('Pending migrations: '.(is_countable($status['pending_migrations']) ? count($status['pending_migrations']) : 'unknown'));
        $this->line('Queued jobs: '.$status['queued_jobs']);
        $this->line('Failed jobs: '.($status['failed_jobs'] ?? 'unknown'));

        return $this->exitCode($status);
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
