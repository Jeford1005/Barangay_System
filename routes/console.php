<?php

use App\Jobs\CreateDatabaseBackup;
use App\Models\Resident;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('residents:check-account-integrity', function () {
    $duplicates = Resident::query()
        ->whereNotNull('user_id')
        ->select('user_id')
        ->groupBy('user_id')
        ->havingRaw('COUNT(*) > 1')
        ->pluck('user_id');

    if ($duplicates->isEmpty()) {
        $this->info('No duplicate resident account links found.');

        return 0;
    }

    $this->error('Duplicate resident account links found:');
    foreach ($duplicates as $userId) {
        $residentIds = Resident::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->pluck('id')
            ->implode(', ');
        $this->line("user_id={$userId}; resident_ids={$residentIds}");
    }
    $this->comment('Resolve these records before adding a unique residents.user_id constraint.');

    return 1;
})->purpose('Check for duplicate resident account links without modifying data');

Schedule::command('data:quality-audit')
    ->dailyAt('02:00')
    ->withoutOverlapping();
Schedule::command('queue:prune-batches --hours=168')
    ->dailyAt('02:30')
    ->withoutOverlapping();

// Backups were manual-only, so nothing was ever created unless an admin
// clicked the button. 03:00 keeps clear of the audit window above.
Schedule::job(new CreateDatabaseBackup(null, null, 'scheduler', 'schedule:run'))
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// Queued jobs can otherwise sit in `jobs` indefinitely when no worker runs.
Schedule::command('queue:prune --hours=24')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::command('system:health --json')
    ->hourlyAt(15)
    ->withoutOverlapping();
