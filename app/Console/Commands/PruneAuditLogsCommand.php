<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Delete audit_log rows older than the retention window, and anonymize PII
 * out of the rows that are kept.
 *
 * Only rows strictly older than the cutoff are removed — recent rows are
 * never touched. The window is configurable via AUDIT_LOG_RETENTION_DAYS
 * (default 365) or --days, with a 30-day floor so a typo can never wipe
 * recent accountability history.
 *
 * The anonymize pass runs over every retained row before the delete (see
 * AuditLog::anonymizeRetainedRow): emails, IPs and actor/user ids are
 * redacted out of both the `properties` payload AND the identifying
 * columns (user_email, actor_email, ip_address take the REDACTED marker;
 * user_id and actor_id go NULL), while event names, timestamps and
 * non-PII properties stay intact. Saves are timestamp-preserving so the
 * retention math stays honest. Pass --skip-anonymize for a delete-only
 * run.
 */
class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audit:prune-logs
                            {--days= : Retention window in days (default: AUDIT_LOG_RETENTION_DAYS or 365)}
                            {--dry-run : Report how many rows would be deleted and anonymized without changing anything}
                            {--skip-anonymize : Delete old rows without anonymizing the retained ones}';

    protected $description = 'Delete audit_logs rows older than the retention window (default 365 days) and anonymize PII in retained rows';

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

        $anonymize = ! $this->option('skip-anonymize');

        if ($this->option('dry-run')) {
            $this->line("Would delete {$count} audit_logs row(s) older than {$cutoff->toDateTimeString()} ({$days} days).");

            if ($anonymize) {
                $this->line("Would anonymize {$this->countAnonymizable($cutoff)} retained audit_logs row(s).");
            }

            return self::SUCCESS;
        }

        $anonymized = $anonymize ? $this->anonymizeRetained($cutoff) : 0;

        $deleted = 0;

        AuditLog::where('occurred_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$deleted): void {
                $deleted += $rows->each->delete()->count();
            });

        $message = "Deleted {$deleted} audit_logs row(s) older than {$cutoff->toDateTimeString()} ({$days} days).";

        if ($anonymize) {
            $message .= " Anonymized {$anonymized} retained row(s).";
        }

        $this->info($message);

        return self::SUCCESS;
    }

    /**
     * Rewrite PII-bearing fields on every row the prune keeps.
     * Returns the number of rows actually changed.
     */
    private function anonymizeRetained(\DateTimeInterface $cutoff): int
    {
        $anonymized = 0;

        AuditLog::where('occurred_at', '>=', $cutoff)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$anonymized): void {
                foreach ($rows as $row) {
                    if (! $row->anonymizeRetainedRow()) {
                        continue;
                    }

                    // Timestamp-preserving save: a normal save() would bump
                    // updated_at on every anonymized row, silently rewriting
                    // the audit trail's own timeline.
                    $row->timestamps = false;
                    $row->save();
                    $anonymized++;
                }
            });

        return $anonymized;
    }

    /**
     * Dry-run counterpart: how many retained rows hold PII that the
     * anonymize pass would rewrite. Reads only, changes nothing — the
     * check runs against a clone so the live models stay pristine.
     */
    private function countAnonymizable(\DateTimeInterface $cutoff): int
    {
        $count = 0;

        AuditLog::where('occurred_at', '>=', $cutoff)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$count): void {
                foreach ($rows as $row) {
                    if ((clone $row)->anonymizeRetainedRow()) {
                        $count++;
                    }
                }
            });

        return $count;
    }
}
