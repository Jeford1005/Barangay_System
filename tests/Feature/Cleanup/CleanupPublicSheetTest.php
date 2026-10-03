<?php

namespace Tests\Feature\Cleanup;

use App\Models\CleanupDrive;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CleanupPublicSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_with_a_valid_signature_sees_one_drive_only(): void
    {
        $shown = CleanupDrive::factory()->create(['title' => 'Shown Drive']);
        $hidden = CleanupDrive::factory()->create(['title' => 'Hidden Drive']);
        $resident = Resident::factory()->create(['first_name' => 'Secret', 'last_name' => 'Volunteer']);
        $shown->participants()->create(['resident_id' => $resident->id, 'attended' => true, 'hours' => 2.5]);

        $url = URL::signedRoute('cleanup.sheet', $shown);

        $this->get($url)
            ->assertOk()
            ->assertSee('Shown Drive')
            ->assertDontSee('Hidden Drive')
            // Aggregates only — never volunteer names.
            ->assertDontSee('Secret Volunteer');
    }

    public function test_guest_without_a_signature_is_refused(): void
    {
        $drive = CleanupDrive::factory()->create();

        $this->get(route('cleanup.sheet', $drive, false))->assertForbidden();
    }

    public function test_swapped_drive_id_breaks_the_signature(): void
    {
        $shown = CleanupDrive::factory()->create();
        $other = CleanupDrive::factory()->create();

        $signed = URL::signedRoute('cleanup.sheet', $shown);
        $tampered = str_replace('/cleanup/'.$shown->id.'/sheet', '/cleanup/'.$other->id.'/sheet', $signed);

        $this->assertNotSame($signed, $tampered);
        $this->get($tampered)->assertForbidden();
    }

    public function test_logbook_needs_view_permission_and_signs_up_walk_ins(): void
    {
        $staff = User::factory()->staff()->create();
        $residentUser = User::factory()->resident()->create();
        $drive = CleanupDrive::factory()->create();
        $walker = Resident::factory()->create(['first_name' => 'Walk', 'last_name' => 'In']);

        $this->actingAs($residentUser)->get(route('cleanup.logbook', $drive))->assertRedirect();
        $this->actingAs($staff)->get(route('cleanup.logbook', $drive))
            ->assertOk()
            ->assertSee('Sign up walk-in');

        $this->actingAs($staff)
            ->post(route('cleanup.join', $drive), ['resident_id' => $walker->id])
            ->assertRedirect();

        $this->assertDatabaseHas('cleanup_participants', [
            'drive_id' => $drive->id,
            'resident_id' => $walker->id,
        ]);
    }
}
