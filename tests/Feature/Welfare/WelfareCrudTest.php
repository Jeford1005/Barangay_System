<?php

namespace Tests\Feature\Welfare;

use App\Models\AuditLog;
use App\Models\Resident;
use App\Models\User;
use App\Models\Welfare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelfareCrudTest extends TestCase
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
            'beneficiary_name' => 'Carmen Reyes',
            'beneficiary_address' => 'Purok 3, Mabini Ave.',
            'beneficiary_phone' => '09171234567',
            'assistance_type' => 'Medical',
            'program_name' => 'Medical Assistance Program',
            'requested_amount' => 5000,
            'status' => 'Requested',
            'request_date' => now()->toDateString(),
        ], $overrides);
    }

    public function test_guests_cannot_access_welfare_pages(): void
    {
        $this->get('/welfare')->assertRedirect('/login');
        $this->get('/welfare/create')->assertRedirect('/login');
        $this->post('/welfare', $this->validPayload())->assertRedirect('/login');
    }

    public function test_residents_are_redirected_away_from_welfare_pages(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident']);

        $this->actingAs($resident)->get('/welfare')->assertRedirect(route('dashboard'));
        $this->actingAs($resident)->post('/welfare', $this->validPayload())->assertRedirect(route('dashboard'));
    }

    public function test_index_lists_requests_with_status_badges(): void
    {
        Welfare::factory()->create(['beneficiary_name' => 'Carmen Reyes', 'status' => 'Requested']);
        Welfare::factory()->approved()->create(['beneficiary_name' => 'Pedro Cruz']);

        $this->actingAs($this->admin)->get('/welfare')
            ->assertOk()
            ->assertSee('Social Welfare Assistance')
            ->assertSee('Carmen Reyes')
            ->assertSee('Pedro Cruz')
            ->assertSee('Requested')
            ->assertSee('Approved');
    }

    public function test_index_shows_pending_counts_and_amount(): void
    {
        Welfare::factory()->count(2)->create(['status' => 'Requested']);
        Welfare::factory()->approved()->create(['approved_amount' => 7500]);

        $this->actingAs($this->admin)->get('/welfare')
            ->assertOk()
            ->assertSee('2 new requests')
            ->assertSee(number_format(7500, 2));
    }

    public function test_search_filters_by_beneficiary_or_program(): void
    {
        Welfare::factory()->create(['beneficiary_name' => 'Carmen Reyes', 'program_name' => 'Medical Assistance Program']);
        Welfare::factory()->create(['beneficiary_name' => 'Ramon Ayudado', 'program_name' => 'Ayuda sa Pamilyang Pilipino']);

        $this->actingAs($this->admin)->get('/welfare?search=Ayuda')
            ->assertOk()
            ->assertSee('Ayuda sa Pamilyang Pilipino')
            ->assertDontSee('Carmen Reyes');
    }

    public function test_index_filters_by_status_and_type(): void
    {
        Welfare::factory()->create(['beneficiary_name' => 'Zelda Deniedcase', 'status' => 'Denied', 'assistance_type' => 'Food']);
        Welfare::factory()->approved()->create(['beneficiary_name' => 'Marcus Approvedcase', 'assistance_type' => 'Medical']);

        $this->actingAs($this->admin)->get('/welfare?status=Approved&assistance_type=Medical')
            ->assertOk()
            ->assertSee('Marcus Approvedcase')
            ->assertDontSee('Zelda Deniedcase');
    }

    public function test_create_renders_the_form(): void
    {
        $this->actingAs($this->admin)->get('/welfare/create')
            ->assertOk()
            ->assertSee('Record Assistance Request');
    }

    public function test_create_names_how_much_of_the_registry_is_listed(): void
    {
        // The linked-resident select is capped at 1000: the form must say so
        // (or give the exact count when under the cap) instead of silently
        // dropping residents past the cap.
        $this->actingAs($this->admin)->get('/welfare/create')
            ->assertOk()
            ->assertSee('on the list');
    }

    public function test_store_creates_a_request(): void
    {
        $response = $this->actingAs($this->admin)->post('/welfare', $this->validPayload([
            'requested_amount' => 3500.50,
        ]));

        $response->assertRedirect('/welfare')->assertSessionHas('success');

        $this->assertDatabaseHas('welfare', [
            'beneficiary_name' => 'Carmen Reyes',
            'assistance_type' => 'Medical',
            'requested_amount' => 3500.50,
            'status' => 'Requested',
        ]);
    }

    public function test_store_stores_zero_when_approved_amount_left_blank(): void
    {
        // Browsers submit an untouched optional field as an empty string, not
        // an absent key — that must store 0 (the column default), never NULL.
        $this->actingAs($this->admin)->post('/welfare', $this->validPayload([
            'approved_amount' => '',
        ]))->assertRedirect('/welfare')->assertSessionHas('success');

        $this->assertDatabaseHas('welfare', [
            'beneficiary_name' => 'Carmen Reyes',
            'status' => 'Requested',
            'approved_amount' => 0,
        ]);
    }

    public function test_store_links_a_registered_resident(): void
    {
        $resident = Resident::factory()->create(['last_name' => 'Villanueva']);

        $this->actingAs($this->admin)->post('/welfare', $this->validPayload([
            'beneficiary_id' => $resident->id,
            'beneficiary_name' => 'Wrong beneficiary name',
            'beneficiary_address' => 'Wrong beneficiary address',
            'beneficiary_phone' => '09170000000',
        ]))->assertRedirect('/welfare');

        $request = Welfare::first();
        $this->assertSame($resident->id, $request->beneficiary_id);
        $this->assertSame($resident->full_name, $request->beneficiary_name);
        $this->assertSame($resident->address, $request->beneficiary_address);
        $this->assertSame($resident->phone_number, $request->beneficiary_phone);
    }

    public function test_edit_keeps_an_archived_linked_resident_visible_and_valid(): void
    {
        $resident = Resident::factory()->create(['status' => 'Archived']);
        $request = Welfare::factory()->create(['beneficiary_id' => $resident->id]);

        $this->actingAs($this->admin)->get(route('welfare.edit', $request))->assertOk()->assertSee('[Archived]');
        $this->actingAs($this->admin)->put(route('welfare.update', $request), $this->validPayload([
            'beneficiary_id' => $resident->id,
        ]))->assertRedirect('/welfare');
    }

    public function test_store_requires_core_fields(): void
    {
        $this->actingAs($this->admin)->post('/welfare', [])
            ->assertSessionHasErrors(['beneficiary_name', 'assistance_type', 'program_name', 'requested_amount', 'status', 'request_date']);

        $this->assertDatabaseCount('welfare', 0);
    }

    public function test_store_rejects_future_request_dates_and_bad_amounts(): void
    {
        $this->actingAs($this->admin)->post('/welfare', $this->validPayload([
            'request_date' => now()->addDay()->toDateString(),
            'requested_amount' => -5,
        ]))->assertSessionHasErrors(['request_date', 'requested_amount']);
    }

    public function test_store_records_an_audit_log_entry(): void
    {
        $this->actingAs($this->admin)->post('/welfare', $this->validPayload());

        $log = AuditLog::where('event', 'welfare.created')->first();

        $this->assertNotNull($log);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('Carmen Reyes', $log->properties['beneficiary']);
    }

    public function test_store_rejects_letters_and_invalid_numeric_values(): void
    {
        $this->actingAs($this->admin)->post('/welfare', $this->validPayload([
            'beneficiary_phone' => 'phone-number',
            'beneficiary_id' => 'not-an-id',
            'requested_amount' => 'many',
            'approved_amount' => '12.345',
        ]))->assertSessionHasErrors([
            'beneficiary_phone',
            'beneficiary_id',
            'requested_amount',
            'approved_amount',
        ]);

        $this->assertDatabaseCount('welfare', 0);
    }

    public function test_edit_renders_the_form(): void
    {
        $request = Welfare::factory()->create(['beneficiary_name' => 'Carmen Reyes']);

        $this->actingAs($this->admin)->get("/welfare/{$request->id}/edit")
            ->assertOk()
            ->assertSee('Edit Assistance — Carmen Reyes');
    }

    public function test_update_changes_a_request(): void
    {
        $request = Welfare::factory()->create();

        $this->actingAs($this->admin)->put("/welfare/{$request->id}", $this->validPayload([
            'status' => 'Under Review',
        ]))->assertRedirect('/welfare')->assertSessionHas('success');

        $this->assertDatabaseHas('welfare', ['id' => $request->id, 'status' => 'Under Review']);
    }

    public function test_approving_requires_date_and_positive_amount(): void
    {
        $request = Welfare::factory()->create();

        $this->actingAs($this->admin)->put("/welfare/{$request->id}", $this->validPayload([
            'status' => 'Approved',
        ]))->assertSessionHasErrors(['approval_date', 'approved_amount']);

        $this->actingAs($this->admin)->put("/welfare/{$request->id}", $this->validPayload([
            'status' => 'Approved',
            'approval_date' => now()->toDateString(),
            'approved_amount' => 0, // not positive
        ]))->assertSessionHasErrors(['approved_amount']);

        $this->actingAs($this->admin)->put("/welfare/{$request->id}", $this->validPayload([
            'status' => 'Approved',
            'approval_date' => now()->toDateString(),
            'approved_amount' => 4200,
        ]))->assertRedirect('/welfare');

        $this->assertDatabaseHas('welfare', ['id' => $request->id, 'status' => 'Approved', 'approved_amount' => 4200]);
    }

    public function test_released_requires_full_trail(): void
    {
        $request = Welfare::factory()->approved()->create();

        $this->actingAs($this->admin)->put("/welfare/{$request->id}", $this->validPayload([
            'status' => 'Released',
            'approval_date' => now()->subDay()->toDateString(),
            'approved_amount' => 4200,
            'release_date' => now()->subDays(2)->toDateString(), // before approval — invalid
        ]))->assertSessionHasErrors(['release_date']);

        $this->actingAs($this->admin)->put("/welfare/{$request->id}", $this->validPayload([
            'status' => 'Released',
            'approval_date' => now()->subDays(2)->toDateString(),
            'approved_amount' => 4200,
            'release_date' => now()->toDateString(),
        ]))->assertRedirect('/welfare');

        $this->assertDatabaseHas('welfare', ['id' => $request->id, 'status' => 'Released']);
    }

    public function test_update_logs_an_audit_entry(): void
    {
        $request = Welfare::factory()->create();

        $this->actingAs($this->admin)->put("/welfare/{$request->id}", $this->validPayload());

        $this->assertDatabaseHas('audit_logs', ['event' => 'welfare.updated', 'user_id' => $this->admin->id]);
    }

    public function test_destroy_soft_deletes_and_logs(): void
    {
        $request = Welfare::factory()->create();

        $response = $this->actingAs($this->admin)->delete("/welfare/{$request->id}");

        $response->assertRedirect('/welfare')->assertSessionHas('success');

        $this->assertSoftDeleted($request);

        $this->assertDatabaseHas('audit_logs', ['event' => 'welfare.deleted', 'user_id' => $this->admin->id]);
    }

    public function test_linked_resident_dropdown_is_populated(): void
    {
        Resident::factory()->create(['first_name' => 'Elena', 'last_name' => 'Marcos']);

        $this->actingAs($this->admin)->get('/welfare/create')
            ->assertOk()
            ->assertSee('Marcos, Elena');
    }

    public function test_update_keeps_manual_edits_when_the_linked_resident_is_unchanged(): void
    {
        $resident = Resident::factory()->create([
            'first_name' => 'Ligaya',
            'last_name' => 'Santos',
            'address' => 'Purok 1, Rizal St.',
            'phone_number' => '09171234567',
        ]);

        $this->actingAs($this->admin)->post('/welfare', $this->validPayload([
            'beneficiary_id' => $resident->id,
        ]))->assertRedirect('/welfare');

        $request = Welfare::firstOrFail();
        $this->assertSame('Ligaya Santos', $request->beneficiary_name);

        // An unrelated save (status review plus corrected contact details)
        // must not re-sync the linked resident over the clerk's edits.
        $this->actingAs($this->admin)->put("/welfare/{$request->id}", $this->validPayload([
            'beneficiary_id' => $resident->id,
            'beneficiary_name' => 'Ligaya S. Santos (corrected)',
            'beneficiary_address' => 'Purok 1, Rizal St., Apt 2',
            'beneficiary_phone' => '09179998888',
            'status' => 'Under Review',
        ]))->assertRedirect('/welfare');

        $this->assertDatabaseHas('welfare', [
            'id' => $request->id,
            'beneficiary_id' => $resident->id,
            'beneficiary_name' => 'Ligaya S. Santos (corrected)',
            'beneficiary_address' => 'Purok 1, Rizal St., Apt 2',
            'beneficiary_phone' => '09179998888',
            'status' => 'Under Review',
        ]);
    }

    public function test_update_resyncs_details_when_the_linked_resident_changes(): void
    {
        $first = Resident::factory()->create([
            'first_name' => 'Ligaya',
            'last_name' => 'Santos',
            'address' => 'Old Address',
            'phone_number' => '09171111111',
        ]);
        $second = Resident::factory()->create([
            'first_name' => 'Ramon',
            'last_name' => 'Cruz',
            'address' => 'Blk 9 Lot 4, Purok 2',
            'phone_number' => '09172222222',
        ]);
        $request = Welfare::factory()->create([
            'status' => 'Requested',
            'beneficiary_id' => $first->id,
            'beneficiary_name' => 'Whatever Manual',
            'beneficiary_address' => 'Whatever Address',
            'beneficiary_phone' => '09170000000',
        ]);

        $this->actingAs($this->admin)->put("/welfare/{$request->id}", $this->validPayload([
            'beneficiary_id' => $second->id,
            'beneficiary_name' => 'Stale Name',
            'beneficiary_address' => 'Stale Address',
            'beneficiary_phone' => '09170000000',
        ]))->assertRedirect('/welfare');

        $this->assertDatabaseHas('welfare', [
            'id' => $request->id,
            'beneficiary_id' => $second->id,
            'beneficiary_name' => 'Ramon Cruz',
            'beneficiary_address' => 'Blk 9 Lot 4, Purok 2',
            'beneficiary_phone' => '09172222222',
        ]);
    }
}
