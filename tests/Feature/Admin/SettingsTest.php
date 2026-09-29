<?php

namespace Tests\Feature\Admin;

use App\Jobs\CreateDatabaseBackup;
use App\Models\Resident;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_residents_cannot_access_settings(): void
    {
        $this->get('/admin/settings')->assertRedirect('/login');

        $resident = User::factory()->resident()->create();
        $this->actingAs($resident)
            ->get('/admin/settings')
            ->assertRedirect(route('dashboard'));
    }

    public function test_security_headers_are_applied_to_every_response(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/residents');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'same-origin');
        $response->assertHeaderMissing('X-Powered-By');
    }

    public function test_admin_can_open_settings_and_maintenance_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertRedirect(route('admin.users.index'));

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('User Accounts')
            ->assertSee('Account Approvals')
            ->assertSee('Mail and Notifications')
            ->assertSee('Audit and Security')
            ->assertSee('System Maintenance');

        $this->actingAs($admin)
            ->get('/admin/settings/maintenance')
            ->assertOk()
            ->assertSee('Database backups')
            ->assertSee('Cache probe')
            ->assertSee('Pending migrations')
            ->assertSee('System requirements');
    }

    public function test_settings_ships_as_a_dialog_with_the_sidebar_trigger(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-settings-open', false)
            ->assertSee('id="settings-dialog"', false)
            ->assertSee('data-settings-frame', false);
    }

    public function test_embed_mode_renders_settings_content_without_app_chrome(): void
    {
        $admin = User::factory()->admin()->create();

        // The hub redirect carries ?embed=1 along, so the frame never
        // lands on a full-chrome page.
        $this->actingAs($admin)
            ->get('/admin/settings?embed=1')
            ->assertRedirect(route('admin.users.index', ['embed' => '1']));

        $this->actingAs($admin)
            ->get('/admin/users?embed=1')
            ->assertOk()
            ->assertSee('User Accounts')
            ->assertDontSee('id="sidebar"', false)
            ->assertDontSee('id="sidebar-toggle"', false) // no dead hamburger
            ->assertDontSee('aria-label="Settings sections"', false) // the dialog owns the nav
            ->assertSee('id="confirm-dialog"', false); // dialogs keep working inside the frame
    }

    public function test_embed_mode_keeps_the_settings_access_rules(): void
    {
        $this->get('/admin/settings?embed=1')->assertRedirect('/login');

        $official = User::factory()->official()->create();

        $this->actingAs($official)
            ->get('/admin/settings?embed=1')
            ->assertRedirect(route('dashboard'));

        // The dialog ships only where its trigger does, so officials never
        // see the settings URLs at all (OfficialAccessTest pins the same).
        $this->actingAs($official)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('/admin/settings', false)
            ->assertDontSee('id="settings-dialog"', false);
    }

    public function test_admin_can_clear_application_caches(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from('/admin/settings/maintenance')
            ->post('/admin/settings/cache/clear')
            ->assertRedirect('/admin/settings/maintenance')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('audit_logs', ['event' => 'system.cache_cleared']);
    }

    public function test_backup_creation_is_queued(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/settings/backups')
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(CreateDatabaseBackup::class);
        $this->assertDatabaseHas('backup_runs', ['status' => 'Queued']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'system.backup_requested']);
    }

    public function test_backup_service_creates_a_private_sqlite_copy(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'barangay-source-');
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'barangay-backups-'.uniqid();
        File::put($source, 'sqlite-test');
        File::ensureDirectoryExists($directory);

        $oldDefault = config('database.default');
        $oldDatabase = config('database.connections.sqlite.database');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $source]);

        try {
            $backup = (new BackupService($directory))->create();

            $this->assertFileExists($directory.DIRECTORY_SEPARATOR.$backup['name']);
            $this->assertSame('sqlite-test', File::get($directory.DIRECTORY_SEPARATOR.$backup['name']));
        } finally {
            config(['database.default' => $oldDefault, 'database.connections.sqlite.database' => $oldDatabase]);
            File::delete($source);
            File::deleteDirectory($directory);
        }
    }

    public function test_backup_service_prunes_old_backups(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'barangay-source-');
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'barangay-backups-'.uniqid();
        File::put($source, 'sqlite-test');
        File::ensureDirectoryExists($directory);
        File::put($directory.DIRECTORY_SEPARATOR.'barangay-20200101-000000.sqlite', 'old');
        File::put($directory.DIRECTORY_SEPARATOR.'barangay-20200102-000000.sqlite', 'old');
        touch($directory.DIRECTORY_SEPARATOR.'barangay-20200101-000000.sqlite', now()->subDays(2)->timestamp);
        touch($directory.DIRECTORY_SEPARATOR.'barangay-20200102-000000.sqlite', now()->subDay()->timestamp);

        $oldDefault = config('database.default');
        $oldDatabase = config('database.connections.sqlite.database');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $source]);

        try {
            (new BackupService($directory, 2))->create();

            $this->assertCount(2, (new BackupService($directory))->all());
            $this->assertFileDoesNotExist($directory.DIRECTORY_SEPARATOR.'barangay-20200101-000000.sqlite');
            $this->assertFileExists($directory.DIRECTORY_SEPARATOR.'barangay-20200102-000000.sqlite');
        } finally {
            config(['database.default' => $oldDefault, 'database.connections.sqlite.database' => $oldDatabase]);
            File::delete($source);
            File::deleteDirectory($directory);
        }
    }

    public function test_duplicate_resident_account_integrity_command_reports_without_deleting(): void
    {
        $user = User::factory()->resident()->create();
        $first = Resident::factory()->create(['user_id' => $user->id]);
        $second = Resident::factory()->create(['user_id' => $user->id]);

        $this->artisan('residents:check-account-integrity')
            ->expectsOutputToContain("user_id={$user->id}; resident_ids={$first->id}, {$second->id}")
            ->assertExitCode(1);

        $this->assertDatabaseHas('residents', ['id' => $first->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('residents', ['id' => $second->id, 'user_id' => $user->id]);
    }

    public function test_backup_download_rejects_path_traversal(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/settings/backups/..%2F.env/download')
            ->assertNotFound();
    }
}
