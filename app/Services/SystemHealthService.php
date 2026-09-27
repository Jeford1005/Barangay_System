<?php

namespace App\Services;

use App\Models\BackupRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SystemHealthService
{
    public function __construct(private readonly BackupService $backups) {}

    /**
     * @return array<string, mixed>
     */
    public function check(): array
    {
        $databaseConnected = false;
        $databaseMessage = null;
        $cacheConnected = false;
        $storageWritable = false;
        $pendingMigrations = null;
        $oldestQueuedJob = null;
        try {
            $queueHeartbeatAt = Cache::get('system.queue.heartbeat');
        } catch (\Throwable) {
            $queueHeartbeatAt = null;
        }

        try {
            DB::select('select 1');
            $databaseConnected = true;
        } catch (\Throwable $exception) {
            $databaseMessage = $exception->getMessage();
        }

        try {
            $key = 'system-health-'.bin2hex(random_bytes(8));
            Cache::put($key, 'ok', 10);
            $cacheConnected = Cache::get($key) === 'ok';
            Cache::forget($key);
        } catch (\Throwable) {
            $cacheConnected = false;
        }

        try {
            $storageWritable = File::isWritable(storage_path());
        } catch (\Throwable) {
            $storageWritable = false;
        }

        try {
            $applied = Schema::getTable('migrations')->pluck('migration')->all();
            $files = collect(File::files(database_path('migrations')))
                ->map(fn ($file) => $file->getFilenameWithoutExtension())
                ->reject(fn ($migration) => in_array($migration, ['create_jobs_table', 'create_cache_table'], true));
            $pendingMigrations = $files->reject(fn ($migration) => in_array($migration, $applied, true))->values();
        } catch (\Throwable) {
            $pendingMigrations = null;
        }

        try {
            $oldestQueuedJob = DB::table('jobs')->min('created_at');
        } catch (\Throwable) {
            $oldestQueuedJob = null;
        }

        $failedJobs = null;
        $queuedJobs = 0;
        $lastBackupRun = null;
        $backups = [];

        // These must stay inside try/catch: the whole point of this report is
        // to describe a broken database rather than throw while describing it.
        try {
            $queuedJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        } catch (\Throwable) {
            $queuedJobs = 0;
        }

        try {
            $failedJobs = Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->count()
                : null;
        } catch (\Throwable) {
            $failedJobs = null;
        }

        try {
            $backups = $this->backups->all();
        } catch (\Throwable) {
            $backups = [];
        }

        try {
            $lastBackupRun = Schema::hasTable('backup_runs')
                ? BackupRun::query()->latest('id')->first()
                : null;
        } catch (\Throwable) {
            $lastBackupRun = null;
        }

        return [
            'database_connected' => $databaseConnected,
            'database_message' => $databaseMessage,
            'cache_connected' => $cacheConnected,
            'storage_writable' => $storageWritable,
            'pending_migrations' => $pendingMigrations,
            'queued_jobs' => $queuedJobs,
            'oldest_queued_job' => $oldestQueuedJob,
            'queue_heartbeat_at' => $queueHeartbeatAt,
            'failed_jobs' => $failedJobs,
            'queue_connection' => (string) config('queue.default'),
            'last_backup' => $backups[0] ?? null,
            'backup_count' => count($backups),
            'last_backup_run' => $lastBackupRun?->only(['id', 'status', 'file_name', 'error_message', 'finished_at']),
        ];
    }
}
