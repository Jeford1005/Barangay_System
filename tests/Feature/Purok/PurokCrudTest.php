<?php

namespace Tests\Feature\Purok;

use App\Models\Household;
use App\Models\Purok;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurokCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_type' => 'admin']);
    }

    public function test_guests_cannot_access_purok_pages(): void
    {
        $this->get('/puroks')->assertRedirect('/login');
        $this->get('/puroks/create')->assertRedirect('/login');
        $this->post('/puroks', ['name' => 'X'])->assertRedirect('/login');
    }

    public function test_residents_are_redirected_away_from_purok_pages(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident']);

        $this->actingAs($resident)->get('/puroks')->assertRedirect(route('dashboard'));
    }

    public function test_index_lists_puroks(): void
    {
        Purok::factory()->create(['name' => 'Bagong Silang', 'code' => 'P1', 'created_by' => $this->admin->id]);
        Purok::factory()->create(['name' => 'San Isidro', 'code' => 'P2', 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin)->get('/puroks')
            ->assertOk()
            ->assertSee('Purok Management')
            ->assertSee('Bagong Silang')
            ->assertSee('San Isidro');
    }

    public function test_search_filters_puroks_by_name(): void
    {
        Purok::factory()->create(['name' => 'Bagong Silang']);
        Purok::factory()->create(['name' => 'San Isidro']);

        $this->actingAs($this->admin)->get('/puroks?search=Silang')
            ->assertOk()
            ->assertSee('Bagong Silang')
            ->assertDontSee('San Isidro');
    }

    public function test_create_renders_the_form(): void
    {
        $this->actingAs($this->admin)->get('/puroks/create')
            ->assertOk()
            ->assertSee('Add Purok');
    }

    public function test_store_creates_a_purok(): void
    {
        $response = $this->actingAs($this->admin)->post('/puroks', [
            'name' => 'Mabuhay',
            'code' => 'P7',
        ]);

        $response->assertRedirect('/puroks')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('puroks', [
            'name' => 'Mabuhay',
            'code' => 'P7',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_store_requires_a_name(): void
    {
        $this->actingAs($this->admin)->post('/puroks', ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('puroks', 0);
    }

    public function test_store_rejects_duplicate_names(): void
    {
        Purok::factory()->create(['name' => 'Mabuhay']);

        $this->actingAs($this->admin)->post('/puroks', ['name' => 'Mabuhay', 'code' => 'PX'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('puroks', 1);
    }

    public function test_edit_renders_the_form(): void
    {
        $purok = Purok::factory()->create(['name' => 'Mabuhay']);

        $this->actingAs($this->admin)->get("/puroks/{$purok->id}/edit")
            ->assertOk()
            ->assertSee('Edit Purok')
            ->assertSee('Mabuhay');
    }

    public function test_update_changes_a_purok(): void
    {
        $purok = Purok::factory()->create(['name' => 'Mabuhay', 'code' => 'P1']);

        $response = $this->actingAs($this->admin)->put("/puroks/{$purok->id}", [
            'name' => 'Mabuhay Renamed',
            'code' => 'P1B',
        ]);

        $response->assertRedirect('/puroks')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('puroks', [
            'id' => $purok->id,
            'name' => 'Mabuhay Renamed',
            'code' => 'P1B',
        ]);
    }

    public function test_update_can_keep_its_own_name(): void
    {
        $purok = Purok::factory()->create(['name' => 'Mabuhay', 'code' => 'P1']);

        $this->actingAs($this->admin)->put("/puroks/{$purok->id}", [
            'name' => 'Mabuhay',
            'code' => 'P2',
        ])->assertRedirect('/puroks')
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();
    }

    public function test_destroy_deletes_an_empty_purok(): void
    {
        $purok = Purok::factory()->create();

        $response = $this->actingAs($this->admin)->delete("/puroks/{$purok->id}");

        $response->assertRedirect('/puroks')
            ->assertSessionHas('success');

        $this->assertModelMissing($purok);
    }

    public function test_destroy_is_blocked_when_a_household_is_attached(): void
    {
        $purok = Purok::factory()->create();
        Household::factory()->create(['purok_id' => $purok->id, 'created_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->delete("/puroks/{$purok->id}");

        $response->assertRedirect('/puroks')
            ->assertSessionHas('error');

        $this->assertModelExists($purok);
    }
}
