<?php

namespace Tests\Feature\Operations;

use App\Jobs\CreateDatabaseBackup;
use App\Models\BackupRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_backup_job_records_a_failed_run(): void
    {
        $run = BackupRun::create(['status' => 'Queued']);
        $job = new CreateDatabaseBackup(null, null, null, null, $run->id);

        $job->failed(new \RuntimeException('disk unavailable'));

        $this->assertSame('Failed', $run->fresh()->status);
        $this->assertSame('disk unavailable', $run->fresh()->error_message);
        $this->assertDatabaseHas('audit_logs', ['event' => 'system.backup_failed']);
    }
}
