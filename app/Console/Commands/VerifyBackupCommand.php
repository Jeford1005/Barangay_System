<?php

namespace App\Console\Commands;

use App\Services\BackupDrillService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Staging-restore dry run: restore the newest backup into a THROWAWAY
 * database, run integrity checks against the copy, record the outcome as a
 * drill audit event, and delete the scratch file.
 *
 * This command never touches the live database — there is intentionally no
 * option to restore OVER production. Run it monthly (the maintenance page
 * and `system:health` nag when the last passing drill is over 30 days old).
 */
class VerifyBackupCommand extends Command
{
    protected $signature = 'backup:verify
                            {--file= : Verify a specific backup file name instead of the newest}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Dry-run restore of the newest backup into a throwaway database (never touches the live database)';

    public function handle(BackupDrillService $drills): int
    {
        try {
            $result = $drills->verifyNewest(
                is_string($this->option('file')) && trim((string) $this->option('file')) !== ''
                    ? trim((string) $this->option('file'))
                    : null
            );
        } catch (Throwable $exception) {
            // The drill must report, never crash: a corrupt backup, a lost
            // file, or a full disk becomes a FAILED drill, not a stack trace.
            $this->error('Backup drill FAILED: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result['ok'] || $result['skipped'] ? self::SUCCESS : self::FAILURE;
        }

        if ($result['file'] !== null) {
            $this->line('Backup: '.$result['file'].' ('.$result['format'].')');
        }

        if ($result['ok']) {
            $this->line('Tables: '.$result['tables'].' · Rows verified: '.$result['rows']);
            $this->info($result['message']);

            return self::SUCCESS;
        }

        if ($result['skipped']) {
            $this->warn($result['message']);

            return self::SUCCESS;
        }

        $this->error('Backup drill FAILED: '.$result['message']);

        return self::FAILURE;
    }
}
