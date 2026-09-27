<?php

namespace Tests\Feature\Archive;

use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\CertificateIssuance;
use App\Models\Household;
use App\Models\Resident;
use App\Models\User;
use App\Models\Welfare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_type' => 'admin']);
    }

    public function test_guests_cannot_access_archive(): void
    {
        $this->get('/archive')->assertRedirect('/login');
        $this->post('/archive/residents/1/restore')->assertRedirect('/login');
        $this->delete('/archive/residents/1')->assertRedirect('/login');
    }

    public function test_residents_are_redirected_away_from_archive(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident']);

        $this->actingAs($resident)->get('/archive')->assertRedirect(route('dashboard'));
    }

    public function test_index_shows_only_soft_deleted_residents(): void
    {
        $trashed = Resident::factory()->create(['first_name' => 'Trashed', 'last_name' => 'Person']);
        $trashed->delete();
        Resident::factory()->create(['first_name' => 'Live', 'last_name' => 'Person']);

        $html = $this->actingAs($this->admin)->get('/archive')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Trashed', $html);
        $this->assertStringNotContainsString('Live', $html);
    }

    public function test_tabs_show_per_type_counts(): void
    {
        Resident::factory()->count(2)->create()->each(fn ($r) => $r->delete());
        Household::factory()->create()->delete();

        $html = $this->actingAs($this->admin)->get('/archive')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Households', $html);
    }

    public function test_search_filters_trashed_residents(): void
    {
        $a = Resident::factory()->create(['first_name' => 'Alpha', 'last_name' => 'One']);
        $a->delete();
        $b = Resident::factory()->create(['first_name' => 'Beta', 'last_name' => 'Two']);
        $b->delete();

        $this->actingAs($this->admin)->get('/archive?search=Alpha')
            ->assertOk()
            ->assertSee('Alpha')
            ->assertDontSee('Beta');
    }

    public function test_welfare_history_cannot_be_permanently_deleted_from_browser(): void
    {
        $welfare = Welfare::factory()->create();
        $welfare->delete();

        $this->actingAs($this->admin)
            ->delete(route('archive.destroy', ['type' => 'welfare', 'id' => $welfare->id]))
            ->assertSessionHasErrors('archive');

        $this->assertSoftDeleted($welfare);
    }

    public function test_unknown_type_404s(): void
    {
        $this->actingAs($this->admin)->get('/archive/unknown')->assertNotFound();
    }

    public function test_restore_returns_a_resident_to_the_active_list(): void
    {
        $resident = Resident::factory()->create(['first_name' => 'Juan', 'last_name' => 'Del Return']);
        $resident->delete();

        $response = $this->actingAs($this->admin)->post('/archive/residents/'.$resident->id.'/restore');

        $response->assertRedirect(route('archive.type', 'residents'))
            ->assertSessionHas('success');

        $this->assertNull($resident->fresh()->deleted_at);

        // Back in the admin resident list.
        $this->actingAs($this->admin)->get('/residents')->assertSee('Del Return');
    }

    public function test_restore_works_for_households_and_blotter(): void
    {
        $household = Household::factory()->create();
        $household->delete();
        $case = Blotter::factory()->create();
        $case->delete();

        $this->actingAs($this->admin)->post('/archive/households/'.$household->id.'/restore')->assertSessionHas('success');
        $this->actingAs($this->admin)->post('/archive/blotter/'.$case->id.'/restore')->assertSessionHas('success');

        $this->assertNull($household->fresh()->deleted_at);
        $this->assertNull($case->fresh()->deleted_at);
    }

    public function test_restore_404s_for_a_record_that_is_not_deleted(): void
    {
        $resident = Resident::factory()->create();

        $this->actingAs($this->admin)->post('/archive/residents/'.$resident->id.'/restore')->assertNotFound();
    }

    public function test_force_delete_purges_permanently(): void
    {
        $resident = Resident::factory()->create(['first_name' => 'Gone', 'last_name' => 'Forever']);
        $resident->delete();

        $response = $this->actingAs($this->admin)->delete('/archive/residents/'.$resident->id);

        $response->assertRedirect(route('archive.type', 'residents'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('residents', ['id' => $resident->id]);
    }

    public function test_purge_is_blocked_while_residents_are_attached_to_a_household(): void
    {
        $household = Household::factory()->create();
        $member = Resident::factory()->create(['household_id' => $household->id]);
        $household->delete();

        $response = $this->actingAs($this->admin)->delete('/archive/households/'.$household->id);

        $response->assertRedirect()
            ->assertSessionHasErrors('archive');

        $this->assertSoftDeleted('households', ['id' => $household->id]);
        $this->assertDatabaseHas('residents', [
            'id' => $member->id,
            'household_id' => $household->id,
        ]);
    }

    public function test_purge_is_blocked_while_a_resident_has_certificate_history(): void
    {
        $resident = Resident::factory()->create();
        $issuance = CertificateIssuance::factory()->create([
            'resident_id' => $resident->id,
        ]);
        $resident->delete();

        $response = $this->actingAs($this->admin)->delete('/archive/residents/'.$resident->id);

        $response->assertRedirect()
            ->assertSessionHasErrors('archive');

        $this->assertSoftDeleted('residents', ['id' => $resident->id]);
        $this->assertDatabaseHas('certificate_issuances', ['id' => $issuance->id]);
    }

    public function test_purge_is_blocked_while_a_resident_is_linked_to_a_user_account(): void
    {
        $user = User::factory()->create(['user_type' => 'resident']);
        $resident = Resident::factory()->create(['user_id' => $user->id]);
        $resident->delete();

        $response = $this->actingAs($this->admin)->delete('/archive/residents/'.$resident->id);

        $response->assertRedirect()
            ->assertSessionHasErrors('archive');

        $this->assertSoftDeleted('residents', ['id' => $resident->id]);
    }

    public function test_restore_and_purge_are_audited(): void
    {
        $resident = Resident::factory()->create(['first_name' => 'Audit', 'last_name' => 'Me']);
        $resident->delete();

        $this->actingAs($this->admin)->post('/archive/residents/'.$resident->id.'/restore');

        // Purge only applies to trashed records — delete again first.
        $resident->delete();
        $this->actingAs($this->admin)->delete('/archive/residents/'.$resident->id);

        $events = AuditLog::whereIn('event', ['archive.restored', 'archive.purged'])
            ->orderBy('id')
            ->pluck('event')
            ->toArray();

        $this->assertSame(['archive.restored', 'archive.purged'], $events);
    }
}
