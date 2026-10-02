<?php

namespace Tests\Feature\Resident;

use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResidentCrudTest extends TestCase
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
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'Male',
            'civil_status' => 'Single',
            'status' => 'Active',
            'birth_date' => '1990-01-15',
            'phone_number' => '09171234567',
            'email' => 'juan@example.com',
            'voter_status' => true,
            'is_household_head' => false,
        ], $overrides);
    }

    public function test_guests_cannot_access_resident_pages(): void
    {
        $this->get('/residents')->assertRedirect('/login');
        $this->get('/residents/create')->assertRedirect('/login');
        $this->post('/residents', $this->validPayload())->assertRedirect('/login');
    }

    public function test_residents_are_redirected_away_from_resident_pages(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident']);

        $this->actingAs($resident)->get('/residents')->assertRedirect(route('dashboard'));
    }

    public function test_index_lists_residents(): void
    {
        Resident::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'created_by' => $this->admin->id]);
        Resident::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin)->get('/residents')
            ->assertOk()
            ->assertSee('Resident Management')
            ->assertSee('Dela Cruz')
            ->assertSee('Santos');
    }

    public function test_search_finds_residents_by_last_name(): void
    {
        Resident::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        Resident::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $this->actingAs($this->admin)->get('/residents?search=Santos')
            ->assertOk()
            ->assertSee('Santos')
            ->assertDontSee('Dela Cruz');
    }

    public function test_search_finds_residents_by_full_name(): void
    {
        Resident::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        Resident::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $this->actingAs($this->admin)->get('/residents?search=Juan Dela')
            ->assertOk()
            ->assertSee('Dela Cruz')
            ->assertDontSee('Santos');
    }

    public function test_index_filters_by_status(): void
    {
        Resident::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'status' => 'Active']);
        Resident::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'status' => 'Archived']);

        $this->actingAs($this->admin)->get('/residents?status=Archived')
            ->assertOk()
            ->assertSee('Santos')
            ->assertDontSee('Dela Cruz');
    }

    public function test_index_filters_by_purok(): void
    {
        $purokA = Purok::factory()->create(['name' => 'Purok Uno']);
        $purokB = Purok::factory()->create(['name' => 'Purok Dos']);

        Resident::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'purok_id' => $purokA->id]);
        Resident::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'purok_id' => $purokB->id]);

        $this->actingAs($this->admin)->get("/residents?purok_id={$purokB->id}")
            ->assertOk()
            ->assertSee('Santos')
            ->assertDontSee('Dela Cruz');
    }

    public function test_create_renders_the_form(): void
    {
        $this->actingAs($this->admin)->get('/residents/create')
            ->assertOk()
            ->assertSee('Register Resident');
    }

    public function test_store_registers_a_resident(): void
    {
        $purok = Purok::factory()->create();

        $response = $this->actingAs($this->admin)->post('/residents', $this->validPayload([
            'purok_id' => $purok->id,
        ]));

        $response->assertRedirect('/residents')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('residents', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'Male',
            'civil_status' => 'Single',
            'status' => 'Active',
            'purok_id' => $purok->id,
            'user_id' => null,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_store_accepts_a_valid_resident_photo(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->post('/residents', $this->validPayload([
            'photo' => UploadedFile::fake()->createWithContent('resident.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')),
        ]))->assertRedirect('/residents');

        $resident = Resident::latest('id')->first();
        $this->assertNotNull($resident->photo);

        // Photos must not land on the web-served public disk.
        Storage::disk('local')->assertExists($resident->photo);
        Storage::disk('public')->assertMissing($resident->photo);
    }

    public function test_resident_photos_are_only_served_to_the_owner_or_the_office(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        $resident = Resident::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($this->admin)->put("/residents/{$resident->id}", $this->validPayload([
            'photo' => UploadedFile::fake()->createWithContent('resident.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')),
        ]))->assertRedirect('/residents');

        $path = $resident->fresh()->photo;
        $this->assertNotNull($path);

        // Staff and the owner may see it.
        $this->actingAs($this->admin)->get("/residents/{$resident->id}/photo")->assertOk();
        $this->actingAs($owner)->get("/residents/{$resident->id}/photo")->assertOk();

        // An unrelated resident may not.
        $stranger = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        $this->actingAs($stranger)->get("/residents/{$resident->id}/photo")->assertForbidden();

        // Neither may a guest.
        $this->get("/residents/{$resident->id}/photo")->assertForbidden();
    }

    public function test_store_defaults_blank_nationality_to_filipino(): void
    {
        $this->actingAs($this->admin)->post('/residents', $this->validPayload([
            'nationality' => '',
        ]))->assertRedirect('/residents');

        $this->assertDatabaseHas('residents', [
            'last_name' => 'Dela Cruz',
            'nationality' => 'Filipino',
        ]);
    }

    public function test_store_requires_the_mandatory_fields(): void
    {
        $response = $this->actingAs($this->admin)->post('/residents', []);

        $response->assertSessionHasErrors(['first_name', 'last_name', 'sex', 'civil_status', 'status']);
        $this->assertDatabaseCount('residents', 0);
    }

    public function test_store_rejects_invalid_choices_and_dates(): void
    {
        $response = $this->actingAs($this->admin)->post('/residents', $this->validPayload([
            'sex' => 'Unknown',
            'civil_status' => 'Complicated',
            'status' => 'Deleted',
            'birth_date' => now()->addDay()->toDateString(), // must be before today
            'email' => 'not-an-email',
        ]));

        $response->assertSessionHasErrors(['sex', 'civil_status', 'status', 'birth_date', 'email']);
        $this->assertDatabaseCount('residents', 0);
    }

    public function test_store_rejects_letters_in_phone_numbers_and_foreign_ids(): void
    {
        $this->actingAs($this->admin)->post('/residents', $this->validPayload([
            'phone_number' => 'phone-number',
            'blood_type' => 'XYZ',
            'purok_id' => 'not-an-id',
            'household_id' => 'not-an-id',
        ]))->assertSessionHasErrors(['phone_number', 'blood_type', 'purok_id', 'household_id']);

        $this->assertDatabaseCount('residents', 0);
    }

    public function test_edit_renders_the_form(): void
    {
        $resident = Resident::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);

        $this->actingAs($this->admin)->get("/residents/{$resident->id}/edit")
            ->assertOk()
            ->assertSee('Edit Resident')
            ->assertSee('Dela Cruz');
    }

    public function test_update_can_uncheck_boolean_fields(): void
    {
        $resident = Resident::factory()->create([
            'voter_status' => true,
            'is_household_head' => true,
        ]);

        $this->actingAs($this->admin)->put("/residents/{$resident->id}", $this->validPayload([
            'voter_status' => false,
            'is_household_head' => false,
        ]))->assertRedirect('/residents');

        $resident->refresh();
        $this->assertFalse($resident->voter_status);
        $this->assertFalse($resident->is_household_head);
    }

    public function test_update_changes_a_resident(): void
    {
        $resident = Resident::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);

        $response = $this->actingAs($this->admin)->put("/residents/{$resident->id}", $this->validPayload([
            'first_name' => 'Juan',
            'last_name' => 'Santos',
            'civil_status' => 'Married',
            'status' => 'Active',
        ]));

        $response->assertRedirect('/residents')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('residents', [
            'id' => $resident->id,
            'first_name' => 'Juan',
            'last_name' => 'Santos',
            'civil_status' => 'Married',
        ]);
    }

    public function test_directory_lists_active_residents_grouped_by_purok_with_counts(): void
    {
        $purok1 = Purok::factory()->create(['name' => 'Purok 1']);
        $purok2 = Purok::factory()->create(['name' => 'Purok 2']);

        Resident::factory()->create([
            'first_name' => 'Maria', 'last_name' => 'Santos',
            'sex' => 'Female', 'voter_status' => true, 'purok_id' => $purok1->id,
        ]);
        Resident::factory()->create([
            'first_name' => 'Ramon', 'last_name' => 'Cruz',
            'sex' => 'Male', 'voter_status' => false, 'purok_id' => $purok1->id,
        ]);
        Resident::factory()->create([
            'first_name' => 'Elena', 'last_name' => 'Reyes',
            'sex' => 'Female', 'purok_id' => $purok2->id,
        ]);
        Resident::factory()->create([
            'first_name' => 'Tomas', 'last_name' => 'Nopurok',
            'sex' => 'Male', 'purok_id' => null,
        ]);
        // Archived residents must not appear in the directory.
        Resident::factory()->create([
            'first_name' => 'Hidden', 'last_name' => 'Archived',
            'status' => 'Archived', 'purok_id' => $purok1->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/residents/directory');

        $response->assertOk();

        $html = $response->getContent();

        // Document landmarks
        $this->assertStringContainsString('Resident Directory', $html);
        $this->assertStringContainsString('Republic of the Philippines', $html);
        $this->assertStringContainsString('Summary by Purok', $html);

        // Per-purok headings with counts (summary table + roster section each show them)
        $this->assertSame(2, substr_count($html, 'Purok 1'));
        $this->assertSame(2, substr_count($html, 'Purok 2'));
        $this->assertStringContainsString('2 residents', $html);

        // Residents listed under their purok, sorted by surname
        $this->assertStringContainsString('Santos, Maria', $html);
        $this->assertStringContainsString('Cruz, Ramon', $html);
        $this->assertStringContainsString('Reyes, Elena', $html);

        // Purok-less residents get their own heading — nobody silently dropped
        $this->assertStringContainsString('No Purok Assigned', $html);
        $this->assertStringContainsString('Nopurok, Tomas', $html);

        // Archived excluded
        $this->assertStringNotContainsString('Archived, Hidden', $html);

        // Certification footer with total
        $this->assertStringContainsString('true and correct list of the active residents', $html);
        $this->assertStringContainsString('totaling 4', $html);

        // Standalone document, not the app shell
        $this->assertStringNotContainsString('x-app-layout', $html);
    }

    public function test_directory_requires_authentication(): void
    {
        $this->get('/residents/directory')->assertRedirect('/login');
    }

    public function test_directory_is_denied_to_residents(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident']);

        $this->actingAs($resident)->get('/residents/directory')
            ->assertRedirect(route('dashboard'));
    }

    public function test_archive_and_restore_resident_are_explicit_and_audited(): void
    {
        $account = User::factory()->resident()->create();
        $resident = Resident::factory()->create(['user_id' => $account->id]);

        $this->actingAs($this->admin)
            ->post(route('residents.archive', $resident))
            ->assertRedirect('/residents');

        $this->assertSame('Archived', $resident->fresh()->status);
        $this->assertTrue($account->fresh()->isSuspended());
        $this->assertDatabaseHas('audit_logs', ['event' => 'resident.archived']);

        $this->actingAs($this->admin)
            ->post(route('residents.restore', $resident))
            ->assertRedirect('/residents');

        $this->assertSame('Active', $resident->fresh()->status);
        $this->assertTrue($account->fresh()->isSuspended(), 'restore must not silently reactivate an account');
        $this->assertDatabaseHas('audit_logs', ['event' => 'resident.restored']);
    }

    public function test_restore_404s_for_a_live_resident_record(): void
    {
        $resident = Resident::factory()->create(['status' => 'Active']);

        $this->actingAs($this->admin)
            ->post(route('residents.restore', $resident))
            ->assertNotFound();

        $this->assertSame('Active', $resident->fresh()->status);
        $this->assertNull($resident->fresh()->deleted_at);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'resident.restored']);
    }

    public function test_store_with_archived_status_lands_soft_deleted(): void
    {
        $this->actingAs($this->admin)->post('/residents', $this->validPayload([
            'first_name' => 'Arch',
            'last_name' => 'Ived',
            'status' => 'Archived',
        ]))->assertRedirect('/residents');

        $resident = Resident::withTrashed()->where('last_name', 'Ived')->firstOrFail();
        $this->assertSame('Archived', $resident->status);
        $this->assertNotNull($resident->deleted_at);
    }

    public function test_resident_index_uses_compact_crud_toolbar(): void
    {
        $this->actingAs($this->admin)->get('/residents')
            ->assertOk()
            ->assertSee(route('residents.create'), false)
            ->assertSee('Add Resident')
            ->assertDontSee('Open directory')
            ->assertDontSee('Print list');

        $this->actingAs($this->admin)->get('/residents?search=Juan')
            ->assertOk()
            ->assertSeeInOrder(['Filter', 'Reset', 'Add Resident']);
    }
}
