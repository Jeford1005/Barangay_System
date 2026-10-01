<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_and_login_renders(): void
    {
        $this->get('/puroks')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_dashboard_and_index_and_create_pages_render(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        foreach ([
            '/dashboard',
            '/puroks',
            '/puroks/create',
            '/residents',
            '/residents/create',
            '/households',
            '/households/create',
        ] as $page) {
            $this->actingAs($user)->get($page)->assertOk();
        }

        // Confirm layout + section content actually flow through.
        $this->actingAs($user)->get('/puroks')->assertSee('Purok Management');
        $this->actingAs($user)->get('/residents')->assertSee('Resident Management');
        $this->actingAs($user)->get('/households')->assertSee('Household Management');
        $this->actingAs($user)->get('/dashboard')->assertSee('Dashboard');
    }

    public function test_crud_create_actions_have_working_links_without_javascript(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);

        foreach ([
            '/residents' => [route('residents.create'), 'resident-dialog'],
            '/households' => [route('households.create'), 'household-dialog'],
            '/puroks' => [route('puroks.create'), 'purok-dialog'],
            '/blotter' => [route('blotter.create'), 'blotter-dialog'],
            '/welfare' => [route('welfare.create'), 'welfare-dialog'],
            '/certificates' => [route('certificates.create'), 'certificate-dialog'],
        ] as $indexRoute => [$createUrl, $dialogId]) {
            $html = $this->actingAs($admin)->get($indexRoute)
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('href="'.$createUrl.'"', $html);
            $this->assertStringContainsString('data-dialog-open="'.$dialogId.'"', $html);
        }
    }

    public function test_id_selects_submit_database_ids_while_displaying_readable_labels(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $purok = Purok::create(['name' => 'Purok 1', 'code' => 'P1', 'created_by' => $admin->id]);
        $household = Household::create([
            'household_code' => 'HH-001',
            'purok_id' => $purok->id,
            'house_type' => 'Single',
            'ownership' => 'Owned',
            'num_members' => 2,
            'status' => 'Occupied',
            'created_by' => $admin->id,
        ]);
        $resident = Resident::create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'Male',
            'civil_status' => 'Single',
            'status' => 'Active',
            'purok_id' => $purok->id,
            'household_id' => $household->id,
            'created_by' => $admin->id,
        ]);

        $html = $this->actingAs($admin)->get('/residents/create')
            ->assertOk()
            ->getContent();

        // The label is HH-001, but the browser must submit the household's
        // numeric foreign key because the backend validates an integer ID.
        $this->assertStringContainsString('value="'.$household->id.'"', $html);
        $this->assertStringContainsString('>'.$household->household_code.'</option>', $html);
        $this->assertStringContainsString('value="'.$purok->id.'"', $html);
        $this->assertStringContainsString('>'.$purok->name.'</option>', $html);
        $this->assertStringNotContainsString('value="'.$household->household_code.'"', $html);
        $this->assertStringNotContainsString('value="'.$purok->name.'"', $html);

        // The same map behavior is used by the other relationship selectors.
        foreach (['/households/create', '/blotter/create', '/welfare/create'] as $page) {
            $pageHtml = $this->actingAs($admin)->get($page)->assertOk()->getContent();
            $this->assertStringContainsString('value="'.$resident->id.'"', $pageHtml);
        }

        // Plain list options still submit their readable value.
        $this->assertStringContainsString('value="Male"', $html);
    }

    public function test_edit_pages_render(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        $purok = Purok::create(['name' => 'Purok 1', 'code' => 'P1', 'created_by' => $user->id]);

        $household = Household::create([
            'household_code' => 'HH-001',
            'purok_id' => $purok->id,
            'house_type' => 'Single',
            'ownership' => 'Owned',
            'num_members' => 3,
            'status' => 'Occupied',
            'created_by' => $user->id,
        ]);

        $resident = Resident::create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'Male',
            'civil_status' => 'Single',
            'status' => 'Active',
            'purok_id' => $purok->id,
            'household_id' => $household->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get("/puroks/{$purok->id}/edit")
            ->assertOk()
            ->assertSee('Edit Purok');

        $this->actingAs($user)->get("/households/{$household->id}/edit")
            ->assertOk()
            ->assertSee('Edit Household');

        $this->actingAs($user)->get("/residents/{$resident->id}/edit")
            ->assertOk()
            ->assertSee('Edit Resident');
    }

    public function test_non_admin_is_redirected_from_module_pages(): void
    {
        $user = User::factory()->create(['user_type' => 'resident']);

        $this->actingAs($user)->get('/puroks')->assertRedirect('/dashboard');
    }

    public function test_analytics_link_only_renders_for_permitted_roles(): void
    {
        // Every fixed role holds analytics.view today; the gate exists so a
        // future/custom role without it never clicks into a 403 page.
        foreach (['admin', 'staff', 'official'] as $role) {
            $user = User::factory()->create(['user_type' => $role]);

            $this->actingAs($user)->get('/dashboard')
                ->assertOk()
                ->assertSee(route('analytics.index'), false);
        }
    }

    public function test_fragment_mode_requires_an_xhr_request(): void
    {
        $user = User::factory()->create(['user_type' => 'admin']);

        // A bare `?fragment=1` on a normal navigation must still render the
        // full shell, otherwise any page could be stripped of its head and
        // scripts by a crafted link.
        $this->actingAs($user)->get('/residents/create?fragment=1')
            ->assertOk()
            ->assertDontSee('data-crud-fragment', false)
            ->assertSee('syncSidebarToViewport', false);

        $this->actingAs($user)
            ->get('/residents/create?fragment=1', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('data-crud-fragment', false)
            ->assertDontSee('syncSidebarToViewport', false);
    }
}
