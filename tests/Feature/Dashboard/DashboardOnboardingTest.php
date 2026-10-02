<?php

namespace Tests\Feature\Dashboard;

use App\Models\Purok;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_install_shows_checklist_instead_of_stat_cards(): void
    {
        $html = $this->actingAs(User::factory()->create(['user_type' => 'admin']))
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        // The checklist replaces the stat grid — never alongside it.
        $this->assertStringContainsString('id="onboarding-checklist"', $html);
        $this->assertStringNotContainsString('id="dashboard-stats"', $html);

        // All four steps, in order, every one still to do.
        $this->assertStringContainsString('Add a purok', $html);
        $this->assertStringContainsString('Add a household', $html);
        $this->assertStringContainsString('Add a resident', $html);
        $this->assertStringContainsString('Review your dashboard', $html);
        $this->assertSame(4, substr_count($html, 'To do'));

        // Admin sees every action link, wired to the same ?open= dialogs as
        // the Quick Actions shortcuts.
        $this->assertStringContainsString('?open=purok', $html);
        $this->assertStringContainsString('?open=household', $html);
        $this->assertStringContainsString('?open=resident', $html);
    }

    public function test_seeded_dashboard_shows_stat_cards_without_checklist(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        Purok::create(['name' => 'Purok 1', 'code' => 'P1', 'created_by' => $admin->id]);

        $html = $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="dashboard-stats"', $html);
        $this->assertStringNotContainsString('id="onboarding-checklist"', $html);
    }

    public function test_checklist_steps_show_done_and_todo_from_live_counts(): void
    {
        $this->actingAs(User::factory()->create(['user_type' => 'admin']));

        $html = (string) $this->blade(
            '<x-onboarding-checklist :purok-count="1" :household-count="0" :resident-count="2" />'
        );

        // Purok + resident steps done, household still to do, review still
        // to do (needs all three).
        $this->assertSame(2, substr_count($html, 'Done'));
        $this->assertSame(2, substr_count($html, 'To do'));

        // Finished steps offer no action link; unfinished permitted steps do.
        $this->assertStringNotContainsString('?open=purok', $html);
        $this->assertStringNotContainsString('?open=resident', $html);
        $this->assertStringContainsString('?open=household', $html);
    }

    public function test_admin_only_step_stays_admin_only(): void
    {
        // Staff can work households and residents but never create puroks.
        $staffHtml = $this->actingAs(User::factory()->create(['user_type' => 'staff']))
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="onboarding-checklist"', $staffHtml);
        $this->assertStringContainsString('Add a purok', $staffHtml);
        $this->assertStringContainsString('Admin only', $staffHtml);
        $this->assertStringNotContainsString('?open=purok', $staffHtml);
        $this->assertStringContainsString('?open=household', $staffHtml);
        $this->assertStringContainsString('?open=resident', $staffHtml);

        // Officials hold neither manage permission: no setup link may 403.
        $officialHtml = $this->actingAs(User::factory()->create(['user_type' => 'official']))
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="onboarding-checklist"', $officialHtml);
        $this->assertStringNotContainsString('?open=', $officialHtml);
    }
}
