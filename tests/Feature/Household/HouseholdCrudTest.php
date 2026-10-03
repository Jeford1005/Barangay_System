<?php

namespace Tests\Feature\Household;

use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdCrudTest extends TestCase
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
            'household_code' => 'HH-100',
            'purok_id' => Purok::factory()->create()->id,
            'house_type' => 'Single',
            'ownership' => 'Owned',
            'num_members' => 4,
            'status' => 'Occupied',
            'street' => 'Rizal Street',
            'lot_area' => '100 sqm',
            'floor_area' => '50 sqm',
        ], $overrides);
    }

    public function test_guests_cannot_access_household_pages(): void
    {
        $this->get('/households')->assertRedirect('/login');
        $this->get('/households/create')->assertRedirect('/login');
        $this->post('/households', $this->validPayload())->assertRedirect('/login');
    }

    public function test_residents_are_redirected_away_from_household_pages(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident']);

        $this->actingAs($resident)->get('/households')->assertRedirect(route('dashboard'));
    }

    public function test_index_lists_households(): void
    {
        Household::factory()->create(['household_code' => 'HH-001', 'created_by' => $this->admin->id]);
        Household::factory()->create(['household_code' => 'HH-002', 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin)->get('/households')
            ->assertOk()
            ->assertSee('Household Management')
            ->assertSee('HH-001')
            ->assertSee('HH-002');
    }

    public function test_search_filters_households_by_code_or_street(): void
    {
        Household::factory()->create(['household_code' => 'HH-001', 'street' => 'Rizal Street']);
        Household::factory()->create(['household_code' => 'HH-002', 'street' => 'Mabini Avenue']);

        $this->actingAs($this->admin)->get('/households?search=Mabini')
            ->assertOk()
            ->assertSee('HH-002')
            ->assertDontSee('HH-001');
    }

    public function test_index_filters_by_purok(): void
    {
        $purokA = Purok::factory()->create(['name' => 'Purok Uno']);
        $purokB = Purok::factory()->create(['name' => 'Purok Dos']);

        Household::factory()->create(['household_code' => 'HH-001', 'purok_id' => $purokA->id]);
        Household::factory()->create(['household_code' => 'HH-002', 'purok_id' => $purokB->id]);

        $this->actingAs($this->admin)->get("/households?purok_id={$purokB->id}")
            ->assertOk()
            ->assertSee('HH-002')
            ->assertDontSee('HH-001');
    }

    public function test_create_renders_the_form(): void
    {
        $this->actingAs($this->admin)->get('/households/create')
            ->assertOk()
            ->assertSee('Add Household')
            ->assertSee('Head Assignment')
            ->assertSee('Head of Household')
            ->assertSee('Additional Information')
            ->assertSee('Remarks')
            ->assertDontSee('>Head of Household</legend>', false)
            ->assertDontSee('>Remarks</legend>', false);
    }

    public function test_store_creates_a_household(): void
    {
        $purok = Purok::factory()->create();

        $response = $this->actingAs($this->admin)->post('/households', $this->validPayload([
            'household_code' => 'HH-001',
            'purok_id' => $purok->id,
        ]));

        $response->assertRedirect('/households')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('households', [
            'household_code' => 'HH-001',
            'purok_id' => $purok->id,
            'num_members' => 4,
            'status' => 'Occupied',
            'lot_area' => '100 sqm',
            'floor_area' => '50 sqm',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_assigning_a_head_synchronizes_the_resident_flag_and_household(): void
    {
        $head = Resident::factory()->create(['is_household_head' => false]);

        $this->actingAs($this->admin)->post('/households', $this->validPayload([
            'household_code' => 'HH-003',
            'head_of_household_id' => $head->id,
        ]))->assertRedirect('/households');

        $household = Household::where('household_code', 'HH-003')->firstOrFail();
        $head->refresh();

        $this->assertSame($head->id, $household->head_of_household_id);
        $this->assertSame($household->id, $head->household_id);
        $this->assertTrue($head->is_household_head);
    }

    public function test_reassigning_a_head_clears_the_previous_head_flag(): void
    {
        $first = Resident::factory()->create(['is_household_head' => false]);
        $second = Resident::factory()->create(['is_household_head' => false]);
        $household = Household::factory()->create([
            'household_code' => 'HH-004',
            'head_of_household_id' => $first->id,
        ]);
        $first->update(['household_id' => $household->id, 'is_household_head' => true]);

        $this->actingAs($this->admin)->put("/households/{$household->id}", $this->validPayload([
            'household_code' => 'HH-004',
            'head_of_household_id' => $second->id,
        ]))->assertRedirect('/households');

        $this->assertFalse($first->fresh()->is_household_head);
        $this->assertTrue($second->fresh()->is_household_head);
        $this->assertSame($second->id, $household->fresh()->head_of_household_id);
    }

    public function test_household_rejects_a_head_assigned_to_another_household(): void
    {
        $head = Resident::factory()->create();
        $otherHousehold = Household::factory()->create(['head_of_household_id' => null]);
        $head->update(['household_id' => $otherHousehold->id]);

        $this->actingAs($this->admin)
            ->from('/households/create')
            ->post('/households', $this->validPayload([
                'household_code' => 'HH-005',
                'head_of_household_id' => $head->id,
            ]))
            ->assertSessionHasErrors('head_of_household_id');

        $this->assertDatabaseMissing('households', ['household_code' => 'HH-005']);
    }

    public function test_resident_head_flag_updates_the_canonical_household_head(): void
    {
        $household = Household::factory()->create(['head_of_household_id' => null]);
        $resident = Resident::factory()->create([
            'household_id' => $household->id,
            'is_household_head' => false,
        ]);

        $this->actingAs($this->admin)->put("/residents/{$resident->id}", [
            'first_name' => $resident->first_name,
            'last_name' => $resident->last_name,
            'sex' => $resident->sex,
            'civil_status' => $resident->civil_status,
            'status' => 'Active',
            'household_id' => $household->id,
            'is_household_head' => true,
        ])->assertRedirect('/residents');

        $this->assertSame($resident->id, $household->fresh()->head_of_household_id);
        $this->assertTrue($resident->fresh()->is_household_head);
    }

    public function test_store_allows_a_household_without_a_purok(): void
    {
        $this->actingAs($this->admin)->post('/households', $this->validPayload([
            'household_code' => 'HH-002',
            'purok_id' => null,
        ]))->assertRedirect('/households');

        $this->assertDatabaseHas('households', [
            'household_code' => 'HH-002',
            'purok_id' => null,
        ]);
    }

    public function test_store_requires_a_unique_household_code(): void
    {
        Household::factory()->create(['household_code' => 'HH-100']);

        $this->actingAs($this->admin)->post('/households', $this->validPayload())
            ->assertSessionHasErrors('household_code');

        $this->assertDatabaseCount('households', 1);
    }

    public function test_store_validates_choices_and_ranges(): void
    {
        $response = $this->actingAs($this->admin)->post('/households', $this->validPayload([
            'household_code' => 'HH-901',
            'house_type' => 'Mansion',          // not in enum
            'ownership' => 'Squatted',          // not in enum
            'status' => 'Burned Down',          // not in enum
            'num_members' => 0,                 // min:1
            'year_built' => 1799,               // min:1800
        ]));

        $response->assertSessionHasErrors(['house_type', 'ownership', 'status', 'num_members', 'year_built']);
        $this->assertDatabaseCount('households', 0);
    }

    public function test_store_rejects_letters_in_numeric_and_identifier_fields(): void
    {
        $this->actingAs($this->admin)->post('/households', $this->validPayload([
            'purok_id' => 'not-an-id',
            'zip_code' => '12A45',
            'year_built' => 'last-year',
            'num_members' => 'many',
            'head_of_household_id' => 'not-an-id',
        ]))->assertSessionHasErrors([
            'purok_id',
            'zip_code',
            'year_built',
            'num_members',
            'head_of_household_id',
        ]);

        $this->assertDatabaseCount('households', 0);
    }

    public function test_edit_renders_the_form(): void
    {
        $household = Household::factory()->create(['household_code' => 'HH-555']);

        $this->actingAs($this->admin)->get("/households/{$household->id}/edit")
            ->assertOk()
            ->assertSee('Edit Household')
            ->assertSee('HH-555');
    }

    public function test_update_changes_a_household(): void
    {
        $household = Household::factory()->create(['household_code' => 'HH-555']);

        $response = $this->actingAs($this->admin)->put("/households/{$household->id}", $this->validPayload([
            'household_code' => 'HH-555', // keep own code — must not trip the unique rule
            'num_members' => 6,
            'status' => 'Vacant',
        ]));

        $response->assertRedirect('/households')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'num_members' => 6,
            'status' => 'Vacant',
        ]);
    }

    public function test_update_rejects_a_code_taken_by_another_household(): void
    {
        Household::factory()->create(['household_code' => 'HH-001']);
        $household = Household::factory()->create(['household_code' => 'HH-002']);

        $this->actingAs($this->admin)->put("/households/{$household->id}", $this->validPayload([
            'household_code' => 'HH-001',
        ]))->assertSessionHasErrors('household_code');
    }

    public function test_destroy_soft_deletes_a_household(): void
    {
        $household = Household::factory()->create();

        $response = $this->actingAs($this->admin)->delete("/households/{$household->id}");

        $response->assertRedirect('/households')
            ->assertSessionHas('success');

        $this->assertSoftDeleted($household);
    }

    public function test_destroy_releases_members_instead_of_stranding_them(): void
    {
        $household = Household::factory()->create();
        $head = Resident::factory()->create([
            'household_id' => $household->id,
            'is_household_head' => true,
        ]);
        $household->update(['head_of_household_id' => $head->id]);
        $member = Resident::factory()->create(['household_id' => $household->id]);
        $archivedMember = Resident::factory()->create(['household_id' => $household->id]);
        $archivedMember->delete();

        $this->actingAs($this->admin)->delete("/households/{$household->id}")
            ->assertRedirect('/households')
            ->assertSessionHas('success');

        $this->assertSoftDeleted($household);
        $this->assertNull(Household::withTrashed()->find($household->id)->head_of_household_id);

        // No resident — live or already-archived — may keep pointing at the
        // trashed household, and nobody keeps a head flag for a household
        // that no longer exists.
        foreach ([$head->id, $member->id, $archivedMember->id] as $residentId) {
            $fresh = Resident::withTrashed()->find($residentId);
            $this->assertNull($fresh->household_id);
            $this->assertFalse((bool) $fresh->is_household_head);
        }

        $this->assertSame(
            0,
            Resident::withTrashed()->where('household_id', $household->id)->count()
        );

        $log = \App\Models\AuditLog::where('event', 'household.deleted')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(3, $log->properties['detached_members']);
    }
}
