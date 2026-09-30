<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only check for residents sharing the same user account link.
 *
 * Extracted from a routes/console.php closure so the check lives in a
 * versioned command class (serializable, testable, no drift from the
 * data quality audit) while keeping the original signature and output.
 */
class CheckResidentAccountIntegrityCommand extends Command
{
    protected $signature = 'residents:check-account-integrity';

    protected $description = 'Check for duplicate resident account links without modifying data';

    public function handle(): int
    {
        $duplicates = DB::table('residents')
            ->whereNotNull('user_id')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('user_id')
            ->pluck('user_id');

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate resident account links found.');

            return self::SUCCESS;
        }

        $this->error('Duplicate resident account links found:');

        foreach ($duplicates as $userId) {
            $residentIds = DB::table('residents')
                ->where('user_id', $userId)
                ->orderBy('id')
                ->pluck('id')
                ->implode(', ');
            $this->line("user_id={$userId}; resident_ids={$residentIds}");
        }

        $this->comment('Resolve these records before adding a unique residents.user_id constraint.');

        return self::FAILURE;
    }
}
