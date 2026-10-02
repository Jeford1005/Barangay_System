<?php

namespace Tests\Feature\Operations;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\BackupDrillService;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

/**
 * Restore-drill coverage: `backup:verify` throwaway-restores the newest
 * backup (or a named one), reports corruption instead of crashing, always
 * removes its scratch file, and feeds the monthly reminder states
 * (never / recent / stale) surfaced on the maintenance page and in
 * `system:health`.
 *
 * Temp files only: backups live in a sys-temp directory bound into
 * BackupService, and the drill scratch directory under storage is swept in
 * tearDown so no test residue survives.
 */
class BackupVerifyTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    private string $drillDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'barangay-backups-'.uniqid();
        File::ensureDirectoryExists($this->directory);

        $directory = $this->directory;
        $this->app->bind(BackupService::class, fn () => new BackupService($directory));

        $this->drillDirectory = app(BackupDrillService::class)->drillDirectory();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        File::deleteDirectory($this->drillDirectory);

        parent::tearDown();
    }

    public function test_drill_succeeds_on_a_generated_sqlite_backup(): void
    {
        // Generate a REAL backup through BackupService from a scratch file
        // database. (The test's own :memory: connection sits inside
        // RefreshDatabase's transaction, where VACUUM INTO is refused — so
        // the service takes its file-copy fallback, which still produces a
        // byte-identical SQLite snapshot for the drill to verify.)
        $source = tempnam(sys_get_temp_dir(), 'barangay-drill-source-');
        $probe = new PDO('sqlite:'.$source);
        $probe->exec('CREATE TABLE probe_users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $probe->exec("INSERT INTO probe_users (name) VALUES ('Ada'), ('Ben')");
        $probe = null;

        $oldDatabase = config('database.connections.sqlite.database');
        config(['database.connections.sqlite.database' => $source]);

        try {
            $backup = (new BackupService($this->directory))->create();
        } finally {
            config(['database.connections.sqlite.database' => $oldDatabase]);
            @unlink($source);
        }

        $this->assertStringEndsWith('.sqlite', $backup['name']);

        $this->artisan('backup:verify')
            ->expectsOutputToContain($backup['name'])
            ->expectsOutputToContain('Tables:')
            ->assertExitCode(0);

        // The reminder timestamp is set, and only by a PASSING drill.
        $this->assertNotNull(Cache::get(BackupDrillService::CACHE_KEY));
        $this->assertSame($backup['name'], Cache::get(BackupDrillService::CACHE_FILE_KEY));

        // Drill audits are distinguishable from real backup events.
        $audit = AuditLog::query()->where('event', 'system.backup_drill_verified')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertTrue($audit->properties['drill'] ?? false);
        $this->assertSame($backup['name'], $audit->properties['file'] ?? null);
        $this->assertDatabaseMissing('backup_runs', ['status' => 'Verified']);

        $this->assertDrillScratchIsGone();
    }

    public function test_corrupt_sqlite_backup_is_reported_not_crashed(): void
    {
        File::put($this->directory.DIRECTORY_SEPARATOR.'barangay-20260101-120000.sqlite', 'this is not a database');

        $this->artisan('backup:verify')
            ->expectsOutputToContain('FAILED')
            ->assertExitCode(1);

        $audit = AuditLog::query()->where('event', 'system.backup_drill_failed')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertTrue($audit->properties['drill'] ?? false);

        // A failed drill must not refresh the reminder.
        $this->assertNull(Cache::get(BackupDrillService::CACHE_KEY));

        $this->assertDrillScratchIsGone();
    }

    public function test_truncated_mysql_dump_fails_while_complete_dump_passes(): void
    {
        File::put(
            $this->directory.DIRECTORY_SEPARATOR.'barangay-20260102-120000.sql',
            implode("\n", [
                '-- Barangay Management System - database backup',
                'SET FOREIGN_KEY_CHECKS = 0;',
                '',
                'DROP TABLE IF EXISTS `users`;',
                'CREATE TABLE `users` (`id` INT PRIMARY KEY);',
                'INSERT INTO `users` (`id`) VALUES',
                '(1);',
                '',
                'SET FOREIGN_KEY_CHECKS = 1;',
            ])
        );

        $this->artisan('backup:verify --file=barangay-20260102-120000.sql')
            ->assertExitCode(0);

        $this->assertNotNull(Cache::get(BackupDrillService::CACHE_KEY));
        $this->assertSame('mysql-dump', Cache::get(BackupDrillService::CACHE_DETAIL_KEY)['format'] ?? null);

        // Chop the closing marker: the dump now looks truncated and must FAIL.
        Cache::flush();

        File::put(
            $this->directory.DIRECTORY_SEPARATOR.'barangay-20260103-120000.sql',
            implode("\n", [
                '-- Barangay Management System - database backup',
                'SET FOREIGN_KEY_CHECKS = 0;',
                '',
                'CREATE TABLE `users` (`id` INT PRIMARY KEY);',
                'INSERT INTO `users` (`id`) VALUES',
                '(1);',
            ])
        );

        $this->artisan('backup:verify --file=barangay-20260103-120000.sql')
            ->expectsOutputToContain('FAILED')
            ->assertExitCode(1);

        $this->assertNull(Cache::get(BackupDrillService::CACHE_KEY));

        $this->assertDrillScratchIsGone();
    }

    public function test_unverifiable_format_is_skipped_gracefully(): void
    {
        File::put($this->directory.DIRECTORY_SEPARATOR.'barangay-20260104-120000.sql', '-- just a comment, not a dump');

        $this->artisan('backup:verify --file=barangay-20260104-120000.sql')
            ->expectsOutputToContain('Skipped')
            ->assertExitCode(0);

        // Skipped is not verified: the reminder keeps nagging.
        $this->assertNull(Cache::get(BackupDrillService::CACHE_KEY));
        $this->assertDatabaseHas('audit_logs', ['event' => 'system.backup_drill_skipped']);

        $this->assertDrillScratchIsGone();
    }

    public function test_drill_with_no_backups_reports_clearly(): void
    {
        $this->artisan('backup:verify')
            ->expectsOutputToContain('No backups exist')
            ->assertExitCode(0);

        $this->assertNull(Cache::get(BackupDrillService::CACHE_KEY));
    }

    public function test_reminder_states_never_recent_and_stale(): void
    {
        $drills = app(BackupDrillService::class);

        $this->assertSame('never', $drills->drillStatus()['state']);

        Cache::put(BackupDrillService::CACHE_KEY, now()->timestamp);
        $this->assertSame('recent', $drills->drillStatus()['state']);

        Cache::put(BackupDrillService::CACHE_KEY, now()->subDays(12)->timestamp);
        $status = $drills->drillStatus();
        $this->assertSame('recent', $status['state']);
        $this->assertSame(12, $status['days_ago']);

        Cache::put(BackupDrillService::CACHE_KEY, now()->subDays(31)->timestamp);
        $status = $drills->drillStatus();
        $this->assertSame('stale', $status['state']);
        $this->assertSame(31, $status['days_ago']);
    }

    public function test_health_command_and_maintenance_page_surface_the_reminder(): void
    {
        // Never verified: both surfaces nag.
        $this->artisan('system:health')
            ->expectsOutputToContain('Backup drill: never verified')
            ->assertExitCode(0);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.maintenance'))
            ->assertOk()
            ->assertSee('Last restore drill')
            ->assertSee('Never verified');

        // Stale drill: warning state on both surfaces.
        Cache::put(BackupDrillService::CACHE_KEY, now()->subDays(45)->timestamp);
        Cache::put(BackupDrillService::CACHE_FILE_KEY, 'barangay-20250101-120000.sqlite');

        // Stale drill: warning state on both surfaces. The health assertion
        // reads the full output because `warn()` renders through mutators
        // (highlighting, wrapping) that split the message across writes —
        // per-write `expectsOutputToContain` cannot see the whole string.
        $this->artisan('system:health')
            ->expectsOutputToContain('Backup drill STALE')
            ->assertExitCode(0);

        $this->assertSame(0, Artisan::call('system:health'));
        $this->assertStringContainsString('backup:verify', Artisan::output());

        $this->actingAs($admin)
            ->get(route('admin.settings.maintenance'))
            ->assertOk()
            ->assertSee('Last verified')
            ->assertSee('backup:verify', false);

        // A fresh drill clears the warning on the page.
        Cache::put(BackupDrillService::CACHE_KEY, now()->timestamp);

        $this->actingAs($admin)
            ->get(route('admin.settings.maintenance'))
            ->assertOk()
            ->assertSee('Last verified')
            ->assertDontSee('No restore drill has ever passed', false);
    }

    private function assertDrillScratchIsGone(): void
    {
        if (! File::isDirectory($this->drillDirectory)) {
            $this->assertTrue(true);

            return;
        }

        $leftovers = collect(File::allFiles($this->drillDirectory))
            ->map(fn ($file) => $file->getFilename())
            ->all();

        $this->assertSame([], $leftovers, 'Drill scratch files must always be removed.');
    }
}
