<?php

namespace Tests\Feature\Blotter;

use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\Official;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlotterCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_type' => 'admin']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'complainant_name' => 'Maria Santos',
            'complainant_address' => 'Purok 1, Rizal St.',
            'complainant_phone' => '09171234567',
            'accused_name' => 'Juan Dela Cruz',
            'accused_address' => 'Purok 2, Mabini Ave.',
            'accused_phone' => '09181234567',
            'complaint_type' => 'Noise Complaint',
            'complaint_date' => now()->toDateString(),
            'complaint_time' => '21:30',
            'alleged_offense' => 'Loud videoke singing past curfew disturbing neighbors.',
            'status' => 'Open',
            'arrest_made' => 'No',
        ], $overrides);
    }

    public function test_guests_cannot_access_blotter_pages(): void
    {
        $this->get('/blotter')->assertRedirect('/login');
        $this->get('/blotter/create')->assertRedirect('/login');
        $this->post('/blotter', $this->validPayload())->assertRedirect('/login');
    }

    public function test_residents_are_redirected_away_from_blotter_pages(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident']);

        $this->actingAs($resident)->get('/blotter')->assertRedirect(route('dashboard'));
        $this->actingAs($resident)->post('/blotter', $this->validPayload())->assertRedirect(route('dashboard'));
    }

    public function test_index_lists_cases_with_status_badges(): void
    {
        Blotter::factory()->create(['case_number' => 'BLTR-2026-0001', 'status' => 'Open']);
        Blotter::factory()->create(['case_number' => 'BLTR-2026-0002', 'status' => 'Resolved']);

        $this->actingAs($this->admin)->get('/blotter')
            ->assertOk()
            ->assertSee('Blotter Records')
            ->assertSee('BLTR-2026-0001')
            ->assertSee('BLTR-2026-0002')
            ->assertSee('Open')
            ->assertSee('Resolved');
    }

    public function test_index_shows_open_case_count(): void
    {
        Blotter::factory()->open()->count(3)->create();

        $this->actingAs($this->admin)->get('/blotter')
            ->assertOk()
            ->assertSee('3 open cases awaiting action');
    }

    public function test_search_filters_by_case_number_party_or_type(): void
    {
        Blotter::factory()->create(['case_number' => 'BLTR-2026-0001', 'complainant_name' => 'Maria Santos']);
        Blotter::factory()->create(['case_number' => 'BLTR-2026-0002', 'complainant_name' => 'Pedro Reyes']);

        $this->actingAs($this->admin)->get('/blotter?search=Reyes')
            ->assertOk()
            ->assertSee('BLTR-2026-0002')
            ->assertDontSee('BLTR-2026-0001');
    }

    public function test_index_filters_by_status(): void
    {
        Blotter::factory()->create(['case_number' => 'BLTR-2026-0001', 'status' => 'Open']);
        Blotter::factory()->create(['case_number' => 'BLTR-2026-0002', 'status' => 'Dismissed']);

        $this->actingAs($this->admin)->get('/blotter?status=Dismissed')
            ->assertOk()
            ->assertSee('BLTR-2026-0002')
            ->assertDontSee('BLTR-2026-0001');
    }

    public function test_create_renders_the_form(): void
    {
        $this->actingAs($this->admin)->get('/blotter/create')
            ->assertOk()
            ->assertSee('Record Blotter Case');
    }

    public function test_store_creates_a_case_with_an_auto_sequential_number(): void
    {
        $response = $this->actingAs($this->admin)->post('/blotter', $this->validPayload());

        $response->assertRedirect('/blotter')->assertSessionHas('success');

        $this->assertDatabaseHas('blotter', [
            'case_number' => 'BLTR-'.now()->format('Y').'-0001',
            'complainant_name' => 'Maria Santos',
            'status' => 'Open',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_store_continues_the_sequence_for_the_year(): void
    {
        Blotter::factory()->create(['case_number' => 'BLTR-'.now()->format('Y').'-0007']);

        $this->actingAs($this->admin)->post('/blotter', $this->validPayload());

        $this->assertDatabaseHas('blotter', ['case_number' => 'BLTR-'.now()->format('Y').'-0008']);
    }

    public function test_store_links_registered_residents(): void
    {
        $complainant = Resident::factory()->create(['last_name' => 'Villanueva']);
        $accused = Resident::factory()->create(['last_name' => 'Bautista']);

        $this->actingAs($this->admin)->post('/blotter', $this->validPayload([
            'complainant_id' => $complainant->id,
            'complainant_name' => 'Wrong complainant name',
            'complainant_address' => 'Wrong complainant address',
            'complainant_phone' => '09170000000',
            'accused_id' => $accused->id,
            'accused_name' => 'Wrong accused name',
            'accused_address' => 'Wrong accused address',
            'accused_phone' => '09180000000',
        ]))->assertRedirect('/blotter');

        $case = Blotter::first();
        $this->assertSame($complainant->id, $case->complainant_id);
        $this->assertSame($complainant->full_name, $case->complainant_name);
        $this->assertSame($complainant->address, $case->complainant_address);
        $this->assertSame($complainant->phone_number, $case->complainant_phone);
        $this->assertSame($accused->id, $case->accused_id);
        $this->assertSame($accused->full_name, $case->accused_name);
        $this->assertSame($accused->address, $case->accused_address);
        $this->assertSame($accused->phone_number, $case->accused_phone);
    }

    public function test_edit_keeps_an_archived_linked_resident_visible_and_valid(): void
    {
        $resident = Resident::factory()->create(['status' => 'Archived']);
        $case = Blotter::factory()->create(['complainant_id' => $resident->id]);

        $this->actingAs($this->admin)->get(route('blotter.edit', $case))->assertOk()->assertSee('[Archived]');
        $this->actingAs($this->admin)->put(route('blotter.update', $case), $this->validPayload([
            'complainant_id' => $resident->id,
        ]))->assertRedirect('/blotter');
    }

    public function test_store_requires_core_fields(): void
    {
        $this->actingAs($this->admin)->post('/blotter', [])
            ->assertSessionHasErrors(['complainant_name', 'accused_name', 'complaint_type', 'complaint_date', 'alleged_offense', 'status', 'arrest_made']);

        $this->assertDatabaseCount('blotter', 0);
    }

    public function test_store_rejects_future_incident_dates(): void
    {
        $this->actingAs($this->admin)->post('/blotter', $this->validPayload([
            'complaint_date' => now()->addDay()->toDateString(),
        ]))->assertSessionHasErrors('complaint_date');

        $this->assertDatabaseCount('blotter', 0);
    }

    public function test_store_rejects_unknown_resident_or_officer_links(): void
    {
        $this->actingAs($this->admin)->post('/blotter', $this->validPayload([
            'complainant_id' => 99999,
            'officer_id' => 99999,
        ]))->assertSessionHasErrors(['complainant_id', 'officer_id']);
    }

    public function test_store_records_an_audit_log_entry(): void
    {
        $this->actingAs($this->admin)->post('/blotter', $this->validPayload());

        $case = Blotter::first();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'blotter.created',
            'user_id' => $this->admin->id,
        ]);

        $log = AuditLog::where('event', 'blotter.created')->first();
        $this->assertSame($case->case_number, $log->properties['case_number']);
        $this->assertNotNull($log->ip_address);
    }

    public function test_store_rejects_letters_in_phone_numbers_and_foreign_ids(): void
    {
        $this->actingAs($this->admin)->post('/blotter', $this->validPayload([
            'complainant_phone' => 'phone-number',
            'accused_phone' => 'phone-number',
            'complainant_id' => 'not-an-id',
            'accused_id' => 'not-an-id',
            'officer_id' => 'not-an-id',
        ]))->assertSessionHasErrors([
            'complainant_phone',
            'accused_phone',
            'complainant_id',
            'accused_id',
            'officer_id',
        ]);

        $this->assertDatabaseCount('blotter', 0);
    }

    public function test_edit_renders_the_form(): void
    {
        $case = Blotter::factory()->create(['case_number' => 'BLTR-2026-0042']);

        $this->actingAs($this->admin)->get("/blotter/{$case->id}/edit")
            ->assertOk()
            ->assertSee('Edit Case BLTR-2026-0042');
    }

    public function test_update_changes_a_case(): void
    {
        $case = Blotter::factory()->create(['case_number' => 'BLTR-2026-0042']);

        $response = $this->actingAs($this->admin)->put("/blotter/{$case->id}", $this->validPayload([
            'status' => 'Pending',
            'investigator' => 'SPO2 Reyes',
        ]));

        $response->assertRedirect('/blotter')->assertSessionHas('success');

        $this->assertDatabaseHas('blotter', [
            'id' => $case->id,
            'status' => 'Pending',
            'investigator' => 'SPO2 Reyes',
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_update_logs_an_audit_entry(): void
    {
        $case = Blotter::factory()->create();

        $this->actingAs($this->admin)->put("/blotter/{$case->id}", $this->validPayload());

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'blotter.updated',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_resolving_requires_a_disposition(): void
    {
        $case = Blotter::factory()->create();

        $this->actingAs($this->admin)->put("/blotter/{$case->id}", $this->validPayload([
            'status' => 'Resolved',
        ]))->assertSessionHasErrors('disposition');

        $this->actingAs($this->admin)->put("/blotter/{$case->id}", $this->validPayload([
            'status' => 'Resolved',
            'disposition' => 'Amicable settlement signed by both parties.',
            'disposition_date' => now()->toDateString(),
        ]))->assertRedirect('/blotter');

        $this->assertDatabaseHas('blotter', ['id' => $case->id, 'status' => 'Resolved']);
    }

    public function test_dismissing_requires_a_disposition(): void
    {
        $case = Blotter::factory()->create();

        $this->actingAs($this->admin)->put("/blotter/{$case->id}", $this->validPayload([
            'status' => 'Dismissed',
        ]))->assertSessionHasErrors('disposition');
    }

    public function test_destroy_soft_deletes_a_case_and_logs_it(): void
    {
        $case = Blotter::factory()->create();

        $response = $this->actingAs($this->admin)->delete("/blotter/{$case->id}");

        $response->assertRedirect('/blotter')->assertSessionHas('success');

        $this->assertSoftDeleted($case);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'blotter.deleted',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_officer_dropdown_lists_officials(): void
    {
        Official::factory()->create(['first_name' => 'Elena', 'last_name' => 'Marcos', 'position' => 'Punong Barangay']);

        $this->actingAs($this->admin)->get('/blotter/create')
            ->assertOk()
            ->assertSee('Elena')
            ->assertSee('Punong Barangay');
    }

    public function test_print_sheet_renders_the_official_form_for_admins(): void
    {
        $officer = Official::factory()->create([
            'first_name' => 'Elena',
            'last_name' => 'Marcos',
            'position' => 'Punong Barangay',
        ]);
        $case = Blotter::factory()->create([
            'case_number' => 'BLTR-2026-0042',
            'complainant_name' => 'Maria Santos',
            'accused_name' => 'Juan Dela Cruz',
            'officer_id' => $officer->id,
            'disposition' => 'Amicable settlement reached; parties reconciled.',
            'disposition_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('blotter.print', $case));

        $response->assertOk();

        $html = $response->getContent();

        // Letterhead and official-form landmarks
        $this->assertStringContainsString('Republic of the Philippines', $html);
        $this->assertStringContainsString('Blotter Case Sheet', $html);
        $this->assertStringContainsString('BLTR-2026-0042', $html);

        // Party and narrative data from the record
        $this->assertStringContainsString('Maria Santos', $html);
        $this->assertStringContainsString('Juan Dela Cruz', $html);
        $this->assertStringContainsString('Amicable settlement reached', $html);

        // Signature blocks and certification
        $this->assertStringContainsString('Handling Officer', $html);
        $this->assertStringContainsString('Punong Barangay', $html);
        $this->assertStringContainsString('true and correct extract', $html);

        // Standalone print CSS, not the app shell
        $this->assertStringContainsString('@media print', $html);
        $this->assertStringNotContainsString('x-app-layout', $html);
    }

    public function test_print_sheet_shows_placeholder_for_missing_disposition(): void
    {
        $case = Blotter::factory()->create(['status' => 'Open', 'disposition' => null]);

        $this->actingAs($this->admin)->get(route('blotter.print', $case))
            ->assertOk()
            ->assertSee('no disposition recorded as of this printing');
    }

    public function test_print_sheet_requires_authentication(): void
    {
        $case = Blotter::factory()->create();

        $this->get(route('blotter.print', $case))->assertRedirect('/login');
    }

    public function test_print_sheet_is_denied_to_residents(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident']);
        $case = Blotter::factory()->create();

        $this->actingAs($resident)->get(route('blotter.print', $case))
            ->assertRedirect(route('dashboard'));
    }

    public function test_index_and_edit_link_to_the_print_sheet(): void
    {
        $case = Blotter::factory()->create();
        $printUrl = route('blotter.print', $case);

        $this->actingAs($this->admin)->get('/blotter')
            ->assertOk()
            ->assertSee($printUrl);

        $this->actingAs($this->admin)->get(route('blotter.edit', $case))
            ->assertOk()
            ->assertSee($printUrl);
    }
}
