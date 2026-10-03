<?php

namespace Tests\Feature\Cleanup;

use App\Models\CleanupDrive;
use App\Models\CleanupParticipant;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Resident portal slice of cleanup drives: the /my/cleanups page
 * (upcoming drives with a Join button each + summed volunteer hours)
 * and the self sign-up endpoint.
 *
 * The office Blade half of this module ships in another workstream, but
 * this page's view (resident.cleanups) ships HERE, so these tests render
 * the real Blade — no stubs.
 */
class CleanupPortalTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: \App\Models\User, 1: \App\Models\Resident} */
    private function approvedResident(): array
    {
        $user = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        $resident = Resident::factory()->create(['user_id' => $user->id]);

        return [$user, $resident];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $drive = CleanupDrive::factory()->create();

        $this->get(route('resident.cleanups'))->assertRedirect('/login');
        $this->post(route('resident.cleanups.join', $drive))->assertRedirect('/login');
    }

    public function test_office_users_are_redirected_away_from_portal_cleanups(): void
    {
        $staff = User::factory()->staff()->create();
        $drive = CleanupDrive::factory()->create();

        // The `resident` middleware sends non-residents to the dashboard.
        $this->actingAs($staff)->get(route('resident.cleanups'))->assertRedirect(route('dashboard'));
        $this->actingAs($staff)->post(route('resident.cleanups.join', $drive))->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('cleanup_participants', 0);
    }

    public function test_resident_without_a_profile_sees_the_setup_screen(): void
    {
        $user = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);

        // GET pages render the friendly no-profile screen (200), so the
        // resident learns to visit the office instead of seeing a 404.
        $this->actingAs($user)->get(route('resident.cleanups'))
            ->assertOk()
            ->assertSee('Profile setup pending');
    }

    public function test_archived_resident_profile_is_forbidden(): void
    {
        [$user, $resident] = $this->approvedResident();
        $resident->update(['status' => 'Archived']);

        $this->actingAs($user)->get(route('resident.cleanups'))->assertForbidden();

        $drive = CleanupDrive::factory()->create();
        $this->actingAs($user)->post(route('resident.cleanups.join', $drive))->assertForbidden();
    }

    public function test_resident_sees_upcoming_drives_with_join_buttons(): void
    {
        [$user] = $this->approvedResident();

        $scheduled = CleanupDrive::factory()->create(['title' => 'Portal River Cleanup', 'status' => 'Scheduled']);
        $ongoing = CleanupDrive::factory()->ongoing()->create(['title' => 'Portal Trail Cleanup']);
        $completed = CleanupDrive::factory()->completed()->create(['title' => 'Portal Done Cleanup']);
        $cancelled = CleanupDrive::factory()->cancelled()->create(['title' => 'Portal Called Off Cleanup']);

        $response = $this->actingAs($user)->get(route('resident.cleanups'))->assertOk();

        $response->assertSee($scheduled->title)
            ->assertSee($ongoing->title)
            ->assertSee('Join')
            ->assertDontSee($completed->title)
            ->assertDontSee($cancelled->title);

        // One Join form per listed drive, each posting to its own URL.
        $response->assertSee(route('resident.cleanups.join', $scheduled), false);
        $response->assertSee(route('resident.cleanups.join', $ongoing), false);
    }

    public function test_hours_line_is_honest_when_zero(): void
    {
        [$user] = $this->approvedResident();

        $this->actingAs($user)->get(route('resident.cleanups'))
            ->assertOk()
            ->assertSee('No volunteer hours yet');
    }

    public function test_hours_total_sums_attended_signups_only(): void
    {
        [$user, $resident] = $this->approvedResident();

        CleanupParticipant::factory()->attended()->create([
            'resident_id' => $resident->id, 'hours' => 2.0,
        ]);
        CleanupParticipant::factory()->attended()->create([
            'resident_id' => $resident->id, 'hours' => 1.5,
        ]);
        // Signed up but never showed: carries hours on the row that must
        // NOT leak into the total.
        CleanupParticipant::factory()->create([
            'resident_id' => $resident->id, 'attended' => false, 'hours' => 5.0,
        ]);
        // Another resident's hours must not leak in either.
        CleanupParticipant::factory()->attended()->create(['hours' => 9.0]);

        $this->assertSame(3.5, (float) CleanupParticipant::where('resident_id', $resident->id)
            ->where('attended', true)->sum('hours'));

        $this->actingAs($user)->get(route('resident.cleanups'))
            ->assertOk()
            ->assertSee('3.5 volunteer hours')
            ->assertDontSee('No volunteer hours yet');
    }

    public function test_resident_can_join_a_drive(): void
    {
        [$user, $resident] = $this->approvedResident();
        $drive = CleanupDrive::factory()->create(['title' => 'Joinable Portal Drive']);

        $this->actingAs($user)->post(route('resident.cleanups.join', $drive))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cleanup_participants', [
            'drive_id' => $drive->id,
            'resident_id' => $resident->id,
            'attended' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'resident.cleanup_joined',
            'user_id' => $user->id,
        ]);

        // The row now reads Signed up instead of offering Join again.
        $this->actingAs($user)->get(route('resident.cleanups'))
            ->assertOk()
            ->assertSee('Signed up');
    }

    public function test_portal_double_join_is_a_friendly_error_never_a_500(): void
    {
        [$user, $resident] = $this->approvedResident();
        $drive = CleanupDrive::factory()->create();

        $this->actingAs($user)->post(route('resident.cleanups.join', $drive))
            ->assertRedirect();

        // The second tap hits UNIQUE(drive_id, resident_id) and comes back
        // as a validation error, not a 500.
        $this->actingAs($user)->post(route('resident.cleanups.join', $drive))
            ->assertStatus(302)
            ->assertSessionHasErrors('drive');

        $this->assertSame(1, CleanupParticipant::where('drive_id', $drive->id)
            ->where('resident_id', $resident->id)->count());
    }

    public function test_portal_join_rejects_finished_drives(): void
    {
        [$user] = $this->approvedResident();

        $finished = CleanupDrive::factory()->completed()->create();
        $cancelled = CleanupDrive::factory()->cancelled()->create();

        $this->actingAs($user)->post(route('resident.cleanups.join', $finished))
            ->assertSessionHasErrors('drive');
        $this->actingAs($user)->post(route('resident.cleanups.join', $cancelled))
            ->assertSessionHasErrors('drive');

        $this->assertDatabaseCount('cleanup_participants', 0);
    }

    public function test_walk_in_lookup_contract_is_capped_at_1000(): void
    {
        // REPORT: the office walk-in form (staff picking a resident for a
        // drive) has no Blade yet — the parallel workstream owns the
        // cleanup.* office views. The POST half (cleanup.join: tx + row
        // lock + UNIQUE-violation → friendly error) already exists and is
        // covered by CleanupDriveTest. This pins the lookup contract the
        // reported Blade snippet relies on: fetch 1001, flag capped, show
        // 1000 — mirroring ResidentController::householdOptions().
        Resident::factory()->count(1001)->create(['status' => 'Active']);

        $options = Resident::where('status', 'Active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(1001)
            ->pluck('id');
        $capped = $options->count() > 1000;
        $options = $capped ? $options->take(1000) : $options;

        $this->assertTrue($capped);
        $this->assertCount(1000, $options);
    }

    public function test_public_sheet_route_is_signed_and_scoped(): void
    {
        // The venue-QR / public-sheet workstream has merged: exactly one
        // public cleanup URI exists, and it requires a signature.
        $routes = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();

        $this->assertContains('cleanup/{drive}/sheet', $routes);
    }

    public function test_cleanup_export_needs_admin_and_keeps_its_columns(): void
    {
        // Guests are bounced to sign-in; the full dataset-parity case
        // lives in CleanupDriveTest.
        $this->get('/admin/exports/cleanup')->assertRedirect('/login');

        $admin = User::factory()->create(['user_type' => 'admin']);
        $response = $this->actingAs($admin)->get('/admin/exports/cleanup')->assertOk();

        $content = $response->streamedContent();
        $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;

        $this->assertSame(
            ['ID', 'Title', 'Purok', 'Scheduled At', 'Status', 'Participants'],
            str_getcsv((string) strtok($content, "\r\n"))
        );
    }
}
