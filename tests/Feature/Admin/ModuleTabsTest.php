<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleTabsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sidebar rows are <a> tags that carry both a title and the nav-row class.
     * The logout button and the settings rail also use nav-row but are not
     * <a>/title pairs, so they never count here.
     */
    private function sidebarRows(string $html): int
    {
        return preg_match_all('/<a [^>]*title="[^"]*"[^>]*class="nav-row /', $html);
    }

    public function test_admin_sidebar_is_eleven_rows_across_three_sections(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        // Dashboard, Residents, Households, Puroks | Blotter, Welfare,
        // Certificates, Reports, Two-factor auth | Archive, Settings.
        $this->assertSame(11, $this->sidebarRows($response->getContent()));

        $response->assertSee('01</span> Main', false);
        $response->assertSee('02</span> Governance', false);
        $response->assertSee('03</span> Administration', false);

        // Folded modules no longer have a sidebar row of their own; they live
        // as tabs inside their parent page instead.
        $response->assertDontSee('title="Certificate Catalog"', false);
        $response->assertDontSee('title="Certificate Requests"', false);
        $response->assertDontSee('title="Resident Corrections"', false);
        $response->assertDontSee('title="Analytics"', false);
    }

    public function test_staff_sidebar_drops_the_administration_section(): void
    {
        $staff = User::factory()->staff()->create();

        $response = $this->actingAs($staff)->get(route('dashboard'))->assertOk();

        // Same list minus Archive and Settings.
        $this->assertSame(9, $this->sidebarRows($response->getContent()));

        $response->assertDontSee('03</span> Administration', false);
        $response->assertDontSee('title="Archive"', false);
        $response->assertDontSee('title="Settings"', false);
    }

    public function test_certificates_page_ships_the_tab_strip_and_hides_catalog_from_staff(): void
    {
        $admin = User::factory()->admin()->create();

        $issued = $this->actingAs($admin)->get(route('certificates.index'))->assertOk();
        $issued->assertSee('aria-label="Module sections"', false);
        $issued->assertSee('/admin/certificate-requests', false);
        $issued->assertSee('/admin/certificate-types', false);
        // The Issued tab is the current one on this page.
        $this->assertMatchesRegularExpression(
            '/<a href="[^"]*\/certificates"\s+aria-current="page"/',
            $issued->getContent()
        );

        $staff = User::factory()->staff()->create();

        $staffIssued = $this->actingAs($staff)->get(route('certificates.index'))->assertOk();
        $staffIssued->assertSee('aria-label="Module sections"', false);
        $staffIssued->assertSee('/admin/certificate-requests', false);
        $staffIssued->assertDontSee('/admin/certificate-types', false);
    }

    public function test_every_folded_module_page_carries_the_tab_strip(): void
    {
        $admin = User::factory()->admin()->create();

        $pages = [
            route('residents.index'),
            route('admin.resident-changes.index'),
            route('admin.certificate-types.index'),
            '/admin/certificate-types/create',
            route('admin.certificate-requests.index'),
            route('reports.index'),
            route('analytics.index'),
        ];

        foreach ($pages as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertSee('aria-label="Module sections"', false);
        }
    }
}
