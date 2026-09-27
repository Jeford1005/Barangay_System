<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\BackupRun;
use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CreateDatabaseBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public ?int $userId,
        public ?string $userEmail,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?int $backupRunId = null,
    ) {}

    public function handle(BackupService $backups): void
    {
        $run = $this->backupRunId
            ? BackupRun::whereKey($this->backupRunId)->lockForUpdate()->first()
            : BackupRun::create([
                'requested_by' => $this->userId,
                'status' => 'Queued',
            ]);

        if (! $run) {
            $run = BackupRun::create([
                'requested_by' => $this->userId,
                'status' => 'Queued',
            ]);
        }

        $run->update(['status' => 'Running', 'started_at' => now()]);

        try {
            $backup = $backups->create();
            $run->update([
                'status' => 'Completed',
                'file_name' => $backup['name'],
                'size_bytes' => $backup['size'] ?? null,
                'finished_at' => now(),
            ]);

            AuditLog::record(
                'system.backup_created',
                $this->userId,
                $this->userEmail,
                $this->ipAddress,
                $this->userAgent,
                ['file' => $backup['name'], 'backup_run_id' => $run->id],
            );
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'Failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->backupRunId) {
            BackupRun::whereKey($this->backupRunId)->update([
                'status' => 'Failed',
                'error_message' => $exception?->getMessage(),
                'finished_at' => now(),
            ]);
        }

        AuditLog::record(
            'system.backup_failed',
            $this->userId,
            $this->userEmail,
            $this->ipAddress,
            $this->userAgent,
            ['error' => $exception?->getMessage(), 'backup_run_id' => $this->backupRunId],
        );
    }
}
