<?php

namespace Tests\Feature\Cleanup;

use App\Http\Controllers\CleanupDriveController;
use App\Models\AuditLog;
use App\Models\CleanupDrive;
use App\Models\CleanupParticipant;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CleanupDriveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var list<string> Stub Blade files created by this test run. */
    private array $stubViews = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_type' => 'admin']);

        // The Blade half of this module ships in another workstream, so the
        // GET pages have no views yet. Stub the three office views only when
        // they are still missing (a real view always wins), purely to let
        // these HTTP tests exercise routing, middleware, and filters
        // end-to-end. Everything created here is removed in tearDown, so the
        // repo never gains Blade files from this slice.
        $stubs = [
            'cleanup.index' => <<<'BLADE'
                @foreach (($drives ?? []) as $drive)
                {{ $drive->title }}|{{ $drive->status }}
                @endforeach
                BLADE,
            'cleanup.create' => 'Create cleanup drive',
            'cleanup.edit' => 'Edit {{ $drive->title }}',
            'cleanup.show' => 'Show {{ $drive->title }}',
        ];

        foreach ($stubs as $name => $content) {
            if (View::exists($name)) {
                continue;
            }

            $path = resource_path('views/'.str_replace('.', '/', $name).'.blade.php');
            $directory = dirname($path);

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($path, $content);
            $this->stubViews[] = $path;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->stubViews as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $directory = resource_path('views/cleanup');

        if (is_dir($directory) && (glob($directory.'/*') ?: []) === []) {
            rmdir($directory);
        }

        parent::tearDown();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Purok 3 Riverbank Cleanup',
            'description' => 'Monthly riverbank clearing with purok volunteers.',
            'purok_id' => null,
            'scheduled_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'status' => 'Scheduled',
        ], $overrides);
    }

    /** @return list<int> */
    private function responseIds(TestResponse $response): array
    {
        return $response->viewData('drives')->getCollection()->pluck('id')->all();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $drive = CleanupDrive::factory()->create();

        $this->get(route('cleanup.index'))->assertRedirect('/login');
        $this->get(route('cleanup.create'))->assertRedirect('/login');
        $this->post(route('cleanup.store'), $this->validPayload())->assertRedirect('/login');
        $this->get(route('cleanup.edit', $drive))->assertRedirect('/login');
        $this->put(route('cleanup.update', $drive), $this->validPayload())->assertRedirect('/login');
        $this->delete(route('cleanup.destroy', $drive))->assertRedirect('/login');
        $this->post(route('cleanup.join', $drive), ['resident_id' => 1])->assertRedirect('/login');
    }

    public function test_residents_are_redirected_away(): void
    {
        $resident = User::factory()->resident()->create();
        $drive = CleanupDrive::factory()->create();

        $this->actingAs($resident)->get(route('cleanup.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($resident)->post(route('cleanup.store'), $this->validPayload())->assertRedirect(route('dashboard'));
        $this->actingAs($resident)->put(route('cleanup.update', $drive), $this->validPayload())->assertRedirect(route('dashboard'));
    }

    public function test_official_can_view_but_cannot_manage(): void
    {
        $official = User::factory()->official()->create();
        $drive = CleanupDrive::factory()->create(['title' => 'Official Read Drive']);

        $this->assertTrue($official->hasPermission('cleanup.view'));
        $this->assertFalse($official->hasPermission('cleanup.manage'));

        $this->actingAs($official)->get(route('cleanup.index'))->assertOk();

        $this->actingAs($official)->post(route('cleanup.store'), $this->validPayload())
            ->assertRedirect(route('dashboard'));
        // Machine clients get the literal 403 instead of the redirect.
        $this->actingAs($official)->postJson(route('cleanup.store'), $this->validPayload())
            ->assertForbidden();
        $this->actingAs($official)->put(route('cleanup.update', $drive), $this->validPayload())
            ->assertRedirect(route('dashboard'));
        $this->actingAs($official)->post(route('cleanup.join', $drive), ['resident_id' => 1])
            ->assertRedirect(route('dashboard'));
        $this->actingAs($official)->delete(route('cleanup.destroy', $drive))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('cleanup_drives', 1);
    }

    public function test_staff_can_manage_but_cannot_destroy(): void
    {
        $staff = User::factory()->staff()->create();

        $this->assertTrue($staff->hasPermission('cleanup.view'));
        $this->assertTrue($staff->hasPermission('cleanup.manage'));

        $this->actingAs($staff)->get(route('cleanup.index'))->assertOk();
        $this->actingAs($staff)->get(route('cleanup.create'))->assertOk();

        $this->actingAs($staff)->post(route('cleanup.store'), $this->validPayload())
            ->assertRedirect(route('cleanup.index'))
            ->assertSessionHas('success');

        $drive = CleanupDrive::firstOrFail();

        $this->actingAs($staff)->get(route('cleanup.edit', $drive))->assertOk();
        $this->actingAs($staff)->put(route('cleanup.update', $drive), $this->validPayload([
            'title' => 'Staff Renamed Drive',
            'status' => 'Ongoing',
        ]))->assertRedirect(route('cleanup.index'));

        $this->assertSame('Staff Renamed Drive', $drive->fresh()->title);

        // Destruction stays administrator-only, mirroring blotter/households.
        $this->actingAs($staff)->delete(route('cleanup.destroy', $drive))
            ->assertRedirect(route('dashboard'));

        $this->assertNotSoftDeleted($drive);
    }

    public function test_admin_store_creates_a_scheduled_drive_and_audits_it(): void
    {
        $purok = Purok::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('cleanup.store'), $this->validPayload([
            'purok_id' => $purok->id,
            // New drives always start Scheduled, whatever was submitted.
            'status' => 'Ongoing',
        ]));

        $response->assertRedirect(route('cleanup.index'))->assertSessionHas('success');

        $drive = CleanupDrive::firstOrFail();

        $this->assertDatabaseHas('cleanup_drives', [
            'id' => $drive->id,
            'title' => 'Purok 3 Riverbank Cleanup',
            'purok_id' => $purok->id,
            'status' => 'Scheduled',
            'created_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'cleanup.created',
            'user_id' => $this->admin->id,
        ]);

        $log = AuditLog::where('event', 'cleanup.created')->firstOrFail();
        $this->assertSame($drive->title, $log->properties['title']);
    }

    public function test_store_validation_rejects_bad_input(): void
    {
        // One attempt per user: write endpoints are throttled (and the
        // office group's own throttle shares the counter, so rapid repeats
        // as one user would 429 instead of validating).
        $full = $this->validPayload();
        $cases = [
            // A blank submission posts no fields at all.
            [[], ['title', 'scheduled_at', 'status'], true],
            [['title' => 'AB'], ['title'], false],
            [['title' => str_repeat('x', 101)], ['title'], false],
            [['purok_id' => 99999], ['purok_id'], false],
            [['scheduled_at' => 'not-a-date'], ['scheduled_at'], false],
            [['scheduled_at' => '2200-01-01 08:00:00'], ['scheduled_at'], false],
        ];

        foreach ($cases as [$overrides, $fields, $blank]) {
            $user = User::factory()->create(['user_type' => 'admin']);

            $this->actingAs($user)->post(route('cleanup.store'), $blank ? [] : array_merge($full, $overrides))
                ->assertSessionHasErrors($fields);
        }

        $this->assertDatabaseCount('cleanup_drives', 0);
    }

    public function test_index_filters_by_search_status_and_purok(): void
    {
        $purokA = Purok::factory()->create(['name' => 'Filter Purok A']);
        $purokB = Purok::factory()->create(['name' => 'Filter Purok B']);

        $matching = CleanupDrive::factory()->create([
            'title' => 'Riverbank Cleanup Alpha',
            'status' => 'Scheduled',
            'purok_id' => $purokA->id,
        ]);
        $otherStatus = CleanupDrive::factory()->create([
            'title' => 'Upland Trail Cleanup Beta',
            'status' => 'Ongoing',
            'purok_id' => $purokB->id,
        ]);
        CleanupDrive::factory()->completed()->create(['title' => 'Coastal Cleanup Gamma']);

        $searchIds = $this->responseIds(
            $this->actingAs($this->admin)->get(route('cleanup.index', ['search' => 'Riverbank']))->assertOk()
        );
        $this->assertSame([$matching->id], $searchIds);

        $statusIds = $this->responseIds(
            $this->actingAs($this->admin)->get(route('cleanup.index', ['status' => 'Ongoing']))->assertOk()
        );
        $this->assertSame([$otherStatus->id], $statusIds);

        $purokIds = $this->responseIds(
            $this->actingAs($this->admin)->get(route('cleanup.index', ['purok_id' => $purokA->id]))->assertOk()
        );
        $this->assertSame([$matching->id], $purokIds);

        // Unknown statuses filter nothing, exactly like the sibling indexes.
        $allIds = $this->responseIds(
            $this->actingAs($this->admin)->get(route('cleanup.index', ['status' => 'Bogus']))->assertOk()
        );
        $this->assertCount(3, $allIds);
    }

    public function test_index_paginates_and_counts_participants(): void
    {
        $drive = CleanupDrive::factory()->create(['title' => 'Counted Drive']);
        $residentA = Resident::factory()->create();
        $residentB = Resident::factory()->create();
        CleanupParticipant::factory()->create(['drive_id' => $drive->id, 'resident_id' => $residentA->id]);
        CleanupParticipant::factory()->create(['drive_id' => $drive->id, 'resident_id' => $residentB->id]);

        CleanupDrive::factory()->count(20)->create();

        $response = $this->actingAs($this->admin)->get(route('cleanup.index'))->assertOk();

        $paginator = $response->viewData('drives');
        $this->assertSame(20, $paginator->count());
        $this->assertSame(21, $paginator->total());
        $this->assertSame(2, (int) $paginator->getCollection()->firstWhere('id', $drive->id)->participants_count);
    }

    public function test_update_advances_the_workflow_and_audits_it(): void
    {
        $drive = CleanupDrive::factory()->create(['status' => 'Scheduled']);

        $this->actingAs($this->admin)->put(route('cleanup.update', $drive), $this->validPayload([
            'title' => $drive->title,
            'status' => 'Ongoing',
        ]))->assertRedirect(route('cleanup.index'));

        $this->assertSame('Ongoing', $drive->fresh()->status);

        $this->actingAs($this->admin)->put(route('cleanup.update', $drive), $this->validPayload([
            'title' => $drive->title,
            'status' => 'Completed',
        ]))->assertRedirect(route('cleanup.index'));

        $this->assertSame('Completed', $drive->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'cleanup.updated',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_update_rejects_skipped_workflow_steps(): void
    {
        $drive = CleanupDrive::factory()->create(['status' => 'Scheduled']);

        // Scheduled jumps to Ongoing first — never straight to Completed.
        $this->actingAs($this->admin)->put(route('cleanup.update', $drive), $this->validPayload([
            'status' => 'Completed',
        ]))->assertSessionHasErrors('status');

        $this->assertSame('Scheduled', $drive->fresh()->status);
    }

    public function test_completed_drives_are_terminal_for_every_edit(): void
    {
        $drive = CleanupDrive::factory()->completed()->create();

        // Even a same-status field correction is blocked: Completed has no
        // reopen path, so the record is history.
        $this->actingAs($this->admin)->put(route('cleanup.update', $drive), $this->validPayload([
            'title' => 'Attempted Rename',
            'status' => 'Completed',
        ]))->assertSessionHasErrors('status');

        $this->assertNotSame('Attempted Rename', $drive->fresh()->title);
    }

    public function test_cancelled_drives_accept_sign_off_but_never_resume(): void
    {
        $drive = CleanupDrive::factory()->create(['status' => 'Ongoing']);

        $this->actingAs($this->admin)->put(route('cleanup.update', $drive), $this->validPayload([
            'status' => 'Cancelled',
        ]))->assertRedirect(route('cleanup.index'));

        $this->assertSame('Cancelled', $drive->fresh()->status);

        $this->actingAs($this->admin)->put(route('cleanup.update', $drive), $this->validPayload([
            'status' => 'Scheduled',
        ]))->assertSessionHasErrors('status');

        // Same-status descriptive edits still work on a cancelled drive.
        $this->actingAs($this->admin)->put(route('cleanup.update', $drive), $this->validPayload([
            'description' => 'Called off for the fiesta week.',
            'status' => 'Cancelled',
        ]))->assertRedirect(route('cleanup.index'));

        $this->assertSame('Called off for the fiesta week.', $drive->fresh()->description);
    }

    public function test_status_transition_map(): void
    {
        $this->assertTrue(CleanupDrive::canTransition('Scheduled', 'Scheduled'));
        $this->assertTrue(CleanupDrive::canTransition('Scheduled', 'Ongoing'));
        $this->assertTrue(CleanupDrive::canTransition('Ongoing', 'Completed'));
        $this->assertTrue(CleanupDrive::canTransition('Ongoing', 'Cancelled'));
        $this->assertTrue(CleanupDrive::canTransition('Completed', 'Completed'));
        $this->assertTrue(CleanupDrive::canTransition('Cancelled', 'Cancelled'));

        $this->assertFalse(CleanupDrive::canTransition('Scheduled', 'Completed'));
        $this->assertFalse(CleanupDrive::canTransition('Completed', 'Scheduled'));
        $this->assertFalse(CleanupDrive::canTransition('Completed', 'Cancelled'));
        $this->assertFalse(CleanupDrive::canTransition('Cancelled', 'Scheduled'));
        $this->assertFalse(CleanupDrive::canTransition('Cancelled', 'Ongoing'));
    }

    public function test_destroy_soft_deletes_and_audits(): void
    {
        $drive = CleanupDrive::factory()->create(['title' => 'Doomed Drive']);

        $this->actingAs($this->admin)->delete(route('cleanup.destroy', $drive))
            ->assertRedirect(route('cleanup.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($drive);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'cleanup.deleted',
            'user_id' => $this->admin->id,
        ]);

        // Trashed drives leave the active index.
        $this->assertNotContains(
            $drive->id,
            $this->responseIds($this->actingAs($this->admin)->get(route('cleanup.index'))->assertOk())
        );
    }

    public function test_join_signs_up_a_resident(): void
    {
        $drive = CleanupDrive::factory()->create(['title' => 'Joinable Drive']);
        $resident = Resident::factory()->create();

        $this->actingAs($this->admin)->post(route('cleanup.join', $drive), ['resident_id' => $resident->id])
            ->assertRedirect(route('cleanup.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cleanup_participants', [
            'drive_id' => $drive->id,
            'resident_id' => $resident->id,
            'attended' => false,
        ]);
    }

    public function test_double_join_is_a_friendly_error_never_a_500(): void
    {
        $drive = CleanupDrive::factory()->create();
        $resident = Resident::factory()->create();

        $this->actingAs($this->admin)->post(route('cleanup.join', $drive), ['resident_id' => $resident->id])
            ->assertRedirect(route('cleanup.index'));

        // The second sign-up hits the UNIQUE(drive_id, resident_id) index
        // and comes back as a validation error, not a 500.
        $this->actingAs($this->admin)->post(route('cleanup.join', $drive), ['resident_id' => $resident->id])
            ->assertStatus(302)
            ->assertSessionHasErrors('resident_id');

        $this->assertSame(1, CleanupParticipant::where('drive_id', $drive->id)->count());
    }

    public function test_join_rejects_unknown_inactive_or_finished_drives(): void
    {
        $drive = CleanupDrive::factory()->create();
        $other = User::factory()->create(['user_type' => 'admin']);

        $this->actingAs($this->admin)->post(route('cleanup.join', $drive), ['resident_id' => 99999])
            ->assertSessionHasErrors('resident_id');

        $this->actingAs($this->admin)->post(route('cleanup.join', $drive), [])
            ->assertSessionHasErrors('resident_id');

        $archived = Resident::factory()->create();
        $archived->delete();

        $this->actingAs($this->admin)->post(route('cleanup.join', $drive), ['resident_id' => $archived->id])
            ->assertSessionHasErrors('resident_id');

        // Fresh user for the rest: join attempts are throttled.
        $finished = CleanupDrive::factory()->completed()->create();
        $resident = Resident::factory()->create();

        $this->actingAs($other)->post(route('cleanup.join', $finished), ['resident_id' => $resident->id])
            ->assertSessionHasErrors('drive');

        $cancelled = CleanupDrive::factory()->cancelled()->create();

        $this->actingAs($other)->post(route('cleanup.join', $cancelled), ['resident_id' => $resident->id])
            ->assertSessionHasErrors('drive');

        $this->assertDatabaseCount('cleanup_participants', 0);
    }

    public function test_archive_round_trip_keeps_status_intact(): void
    {
        $drive = CleanupDrive::factory()->create([
            'title' => 'Archived Drive',
            'status' => 'Ongoing',
        ]);

        $this->actingAs($this->admin)->delete(route('cleanup.destroy', $drive))
            ->assertRedirect(route('cleanup.index'));

        $this->assertSoftDeleted($drive);

        // The archive lists the new type without crashing.
        $this->actingAs($this->admin)->get('/archive/cleanup')->assertOk();

        $this->actingAs($this->admin)->post(route('archive.restore', ['type' => 'cleanup', 'id' => $drive->id]))
            ->assertRedirect(route('archive.type', 'cleanup'))
            ->assertSessionHas('success');

        $drive->refresh();
        $this->assertNull($drive->deleted_at);
        // Restore never rewrites the workflow status.
        $this->assertSame('Ongoing', $drive->status);

        $this->assertContains(
            $drive->id,
            $this->responseIds($this->actingAs($this->admin)->get(route('cleanup.index'))->assertOk())
        );
    }

    public function test_archive_purge_erases_the_drive_with_its_signups(): void
    {
        $drive = CleanupDrive::factory()->create();
        CleanupParticipant::factory()->count(2)->create(['drive_id' => $drive->id]);

        $this->actingAs($this->admin)->delete(route('cleanup.destroy', $drive));

        $this->actingAs($this->admin)->delete(route('archive.destroy', ['type' => 'cleanup', 'id' => $drive->id]))
            ->assertRedirect(route('archive.type', 'cleanup'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('cleanup_drives', ['id' => $drive->id]);
        $this->assertSame(0, CleanupParticipant::where('drive_id', $drive->id)->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'archive.purged']);
    }

    public function test_cleanup_export_dataset_has_parity_with_the_index(): void
    {
        if (! app('router')->getRoutes()->getByName('admin.exports.residents')) {
            require base_path('routes/admin-exports.php');
        }

        $exports = app(ExportService::class);

        $this->assertTrue($exports->supports('cleanup'));
        $this->assertSame(
            ['ID', 'Title', 'Purok', 'Scheduled At', 'Status', 'Participants'],
            $exports->headers('cleanup')
        );

        $purok = Purok::factory()->create(['name' => 'Export Purok']);
        $matching = CleanupDrive::factory()->create([
            'title' => 'Export River Cleanup',
            'status' => 'Scheduled',
            'purok_id' => $purok->id,
            'scheduled_at' => '2026-11-08 08:00:00',
        ]);
        CleanupParticipant::factory()->count(2)->create(['drive_id' => $matching->id]);
        CleanupDrive::factory()->create(['title' => 'Export Upland Cleanup', 'status' => 'Completed']);

        $response = $this->actingAs($this->admin)->get('/admin/exports/cleanup')->assertOk();
        $rows = $this->dataRows($response);

        $this->assertCount(2, $rows);
        $this->assertSame(
            ['ID', 'Title', 'Purok', 'Scheduled At', 'Status', 'Participants'],
            $this->firstCsvRow($response)
        );

        $matchingRow = collect($rows)->firstWhere(fn (array $row): bool => $row[0] === (string) $matching->id);
        $this->assertSame('Export River Cleanup', $matchingRow[1]);
        $this->assertSame('Export Purok', $matchingRow[2]);
        $this->assertSame('Scheduled', $matchingRow[4]);
        $this->assertSame('2', $matchingRow[5]);

        // The export honors the same search/status/purok filters as the index.
        $filtered = $this->dataRows($this->export('cleanup', [
            'search' => 'River',
            'status' => 'Scheduled',
            'purok_id' => $purok->id,
        ]));
        $this->assertSame([(string) $matching->id], array_column($filtered, 0));

        // Unknown statuses filter nothing, exactly like the index.
        $this->assertCount(2, $this->dataRows($this->export('cleanup', ['status' => 'Bogus'])));
    }

    public function test_dashboard_surfaces_pending_cleanup_drives(): void
    {
        CleanupDrive::factory()->create(['status' => 'Scheduled']);
        CleanupDrive::factory()->ongoing()->create();
        CleanupDrive::factory()->completed()->create();

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Scheduled cleanups');
    }

    public function test_show_returns_the_drive_with_participants_for_reuse(): void
    {
        $drive = CleanupDrive::factory()->create();
        CleanupParticipant::factory()->create(['drive_id' => $drive->id]);

        // No route points at show() yet (the venue-QR / public-sheet
        // workstream mounts its own), so call the action directly. Building
        // the view never renders it, so no Blade file is needed.
        $view = (new CleanupDriveController)->show($drive);

        $this->assertInstanceOf(\Illuminate\View\View::class, $view);

        $shown = $view->getData()['drive'];
        $this->assertSame($drive->id, $shown->id);
        $this->assertTrue($shown->relationLoaded('participants'));
        $this->assertTrue($shown->participants->first()->relationLoaded('resident'));
        $this->assertSame(1, (int) $shown->participants_count);
    }

    private function export(string $dataset, array $query = []): TestResponse
    {
        $url = '/admin/exports/'.$dataset;

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $this->actingAs($this->admin)->get($url);
    }

    /** @return list<string> */
    private function firstCsvRow(TestResponse $response): array
    {
        $content = $response->streamedContent();
        $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
        $line = strtok($content, "\r\n");

        return str_getcsv((string) $line);
    }

    /** @return list<list<string>> */
    private function dataRows(TestResponse $response): array
    {
        $content = $response->streamedContent();
        $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $rows = array_map(static fn (string $line): array => str_getcsv($line), $lines);
        $dataRows = array_slice($rows, 1);

        return array_values(array_filter(
            $dataRows,
            static fn (array $row): bool => collect($row)->contains(static fn (mixed $value): bool => $value !== null && $value !== ''),
        ));
    }

    public function test_create_and_edit_pages_are_gated_by_manage_permission(): void
    {
        $drive = CleanupDrive::factory()->create();
        $staff = User::factory()->staff()->create();
        $official = User::factory()->official()->create();

        // Guests bounce to sign-in on every write page (asserted first:
        // actingAs persists for later calls in this test, so there are no
        // guests left after an office login below).
        $this->get(route('cleanup.create'))->assertRedirect('/login');
        $this->get(route('cleanup.edit', $drive))->assertRedirect('/login');

        // Officials read the index but never open the write pages: the
        // `resident`/`permission` middleware sends browser traffic to the
        // dashboard (machine clients get the literal 403, pinned above).
        $this->actingAs($official)->get(route('cleanup.create'))->assertRedirect(route('dashboard'));
        $this->actingAs($official)->get(route('cleanup.edit', $drive))->assertRedirect(route('dashboard'));
        $this->actingAs($official)->postJson(route('cleanup.store'), $this->validPayload())->assertForbidden();

        // Staff (cleanup.manage) may open both pages.
        $this->actingAs($staff)->get(route('cleanup.create'))->assertOk();
        $this->actingAs($staff)->get(route('cleanup.edit', $drive))->assertOk();
    }

    public function test_staff_checks_in_a_volunteer_with_hours(): void
    {
        $staff = User::factory()->staff()->create();
        $drive = CleanupDrive::factory()->create(['status' => 'Ongoing']);
        $resident = Resident::factory()->create();
        $participant = CleanupParticipant::factory()->create([
            'drive_id' => $drive->id,
            'resident_id' => $resident->id,
            'attended' => false,
        ]);

        $this->actingAs($staff)
            ->post(route('cleanup.check-in', [$drive, $participant]), ['hours' => 2.5])
            ->assertRedirect(route('cleanup.logbook', $drive));

        $participant->refresh();
        $this->assertTrue($participant->attended);
        $this->assertNotNull($participant->checked_in_at);
        $this->assertEquals(2.5, (float) $participant->hours);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cleanup.checked_in']);
    }

    public function test_check_in_is_guarded_and_idempotent(): void
    {
        $staff = User::factory()->staff()->create();
        $resident = User::factory()->resident()->create();
        $drive = CleanupDrive::factory()->create(['status' => 'Ongoing']);
        $other = CleanupDrive::factory()->create(['status' => 'Ongoing']);
        $participant = CleanupParticipant::factory()->create([
            'drive_id' => $drive->id,
            'resident_id' => Resident::factory()->create()->id,
        ]);

        // Guests bounce to sign-in; residents without the permission bounce out.
        $this->post(route('cleanup.check-in', [$drive, $participant]))->assertRedirect('/login');
        $this->actingAs($resident)->post(route('cleanup.check-in', [$drive, $participant]))->assertRedirect();

        // A participant from another drive is not checkable here.
        $this->actingAs($staff)
            ->post(route('cleanup.check-in', [$other, $participant]))
            ->assertNotFound();

        // Bad hours rejected; re-check keeps the first check-in time.
        $this->actingAs($staff)
            ->post(route('cleanup.check-in', [$drive, $participant]), ['hours' => -1])
            ->assertSessionHasErrors('hours');

        $this->actingAs($staff)
            ->post(route('cleanup.check-in', [$drive, $participant]), ['hours' => 3])
            ->assertRedirect();
        $first = $participant->fresh()->checked_in_at;

        $this->actingAs($staff)
            ->post(route('cleanup.check-in', [$drive, $participant]), ['hours' => 4])
            ->assertRedirect();
        $this->assertEquals(4.0, (float) $participant->fresh()->hours);
        $this->assertEquals($first->toDateTimeString(), $participant->fresh()->checked_in_at->toDateTimeString());
        $this->assertSame(1, CleanupParticipant::where('drive_id', $drive->id)->count());
    }

    public function test_cancelled_drives_refuse_check_in(): void
    {
        $staff = User::factory()->staff()->create();
        $drive = CleanupDrive::factory()->create(['status' => 'Cancelled']);
        $participant = CleanupParticipant::factory()->create([
            'drive_id' => $drive->id,
            'resident_id' => Resident::factory()->create()->id,
        ]);

        $this->actingAs($staff)
            ->post(route('cleanup.check-in', [$drive, $participant]))
            ->assertSessionHasErrors('drive');

        $this->assertFalse($participant->fresh()->attended);
    }

    public function test_logbook_shows_update_form_on_attended_rows_and_any_error(): void
    {
        $staff = User::factory()->staff()->create();
        $drive = CleanupDrive::factory()->create(['status' => 'Ongoing']);
        $participant = CleanupParticipant::factory()->create([
            'drive_id' => $drive->id,
            'resident_id' => Resident::factory()->create()->id,
            'attended' => true,
            'hours' => 2.5,
        ]);

        $html = $this->actingAs($staff)->get(route('cleanup.logbook', $drive))
            ->assertOk()
            ->getContent();

        // Hours correction path stays visible after check-in, prefilled.
        $this->assertStringContainsString('>Update<', $html);
        $this->assertStringContainsString('value="2.5"', $html);

        // Any error (not just hours) renders in the roster slot.
        $this->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put(
            'default', new \Illuminate\Support\MessageBag(['drive' => 'Gone'])
        )]);
        $this->actingAs($staff)->get(route('cleanup.logbook', $drive))
            ->assertOk()
            ->assertSee('Gone');
    }
}
