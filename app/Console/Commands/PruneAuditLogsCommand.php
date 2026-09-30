<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Delete audit_log rows older than the retention window.
 *
 * Only rows strictly older than the cutoff are removed — recent rows are
 * never touched. The window is configurable via AUDIT_LOG_RETENTION_DAYS
 * (default 365) or --days, with a 30-day floor so a typo can never wipe
 * recent accountability history.
 */
class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audit:prune-logs
                            {--days= : Retention window in days (default: AUDIT_LOG_RETENTION_DAYS or 365)}
                            {--dry-run : Report how many rows would be deleted without deleting}';

    protected $description = 'Delete audit_logs rows older than the retention window (default 365 days)';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) env('AUDIT_LOG_RETENTION_DAYS', 365);

        if ($days < 30) {
            $this->error('Refusing to prune: the retention window must be at least 30 days.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $count = (clone AuditLog::query())
            ->where('occurred_at', '<', $cutoff)
            ->count();

        if ($this->option('dry-run')) {
            $this->line("Would delete {$count} audit_logs row(s) older than {$cutoff->toDateTimeString()} ({$days} days).");

            return self::SUCCESS;
        }

        $deleted = 0;

        AuditLog::where('occurred_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$deleted): void {
                $deleted += $rows->each->delete()->count();
            });

        $this->info("Deleted {$deleted} audit_logs row(s) older than {$cutoff->toDateTimeString()} ({$days} days).");

        return self::SUCCESS;
    }
}
