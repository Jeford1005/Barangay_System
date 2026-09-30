<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Backup happy path over HTTP: an existing backup downloads with its bytes
 * intact and deletes cleanly, both audited. Creation itself is queued
 * (SettingsTest::test_backup_creation_is_queued) and the service-level copy
 * is covered by BackupServiceTest, so this suite pins the two routes that
 * had only failure coverage (path traversal → 404).
 *
 * All URLs use route() helpers so no host is ever hardcoded.
 */
class BackupHappyPathTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'barangay-backups-'.uniqid();
        File::ensureDirectoryExists($this->directory);

        $directory = $this->directory;
        $this->app->bind(BackupService::class, fn () => new BackupService($directory));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_admin_can_download_a_backup_and_it_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        File::put($this->directory.DIRECTORY_SEPARATOR.'barangay-20260101-120000.sql', '-- happy path backup');

        $response = $this->actingAs($admin)->get(
            route('admin.settings.backups.download', 'barangay-20260101-120000.sql')
        );

        $response->assertOk();
        $response->assertDownload('barangay-20260101-120000.sql');
        $this->assertDatabaseHas('audit_logs', ['event' => 'system.backup_downloaded']);
    }

    public function test_admin_can_delete_a_backup_and_it_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        File::put($this->directory.DIRECTORY_SEPARATOR.'barangay-20260102-120000.sql', '-- delete me');

        $this->actingAs($admin)
            ->from(route('admin.settings.maintenance'))
            ->delete(route('admin.settings.backups.destroy', 'barangay-20260102-120000.sql'))
            ->assertRedirect();

        $this->assertFileDoesNotExist($this->directory.DIRECTORY_SEPARATOR.'barangay-20260102-120000.sql');
        $this->assertDatabaseHas('audit_logs', ['event' => 'system.backup_deleted']);
    }

    public function test_guests_and_residents_cannot_download_or_delete_backups(): void
    {
        File::put($this->directory.DIRECTORY_SEPARATOR.'barangay-20260103-120000.sql', '-- protected');

        $this->get(route('admin.settings.backups.download', 'barangay-20260103-120000.sql'))
            ->assertRedirect(route('login'));
        $this->delete(route('admin.settings.backups.destroy', 'barangay-20260103-120000.sql'))
            ->assertRedirect(route('login'));

        $resident = User::factory()->resident()->create();

        $this->actingAs($resident)
            ->get(route('admin.settings.backups.download', 'barangay-20260103-120000.sql'))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($resident)
            ->delete(route('admin.settings.backups.destroy', 'barangay-20260103-120000.sql'))
            ->assertRedirect(route('dashboard'));

        // Nothing leaked and nothing removed.
        $this->assertFileExists($this->directory.DIRECTORY_SEPARATOR.'barangay-20260103-120000.sql');
        $this->assertDatabaseMissing('audit_logs', ['event' => 'system.backup_downloaded']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'system.backup_deleted']);
    }
}
