<?php

use App\Jobs\CreateDatabaseBackup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('data:quality-audit')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('queue:prune-batches --hours=168')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();

// Backups were manual-only, so nothing was ever created unless an admin
// clicked the button. 03:00 keeps clear of the audit window above.
Schedule::job(new CreateDatabaseBackup(null, null, 'scheduler', 'schedule:run'))
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// Only expired failed-job records are pruned; pending rows in `jobs` are
// never deleted, so actionable work is always kept.
Schedule::command('queue:prune-failed --hours=24')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('system:health --json')
    ->hourlyAt(15)
    ->withoutOverlapping()
    ->onOneServer();
