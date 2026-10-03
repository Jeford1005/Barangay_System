<?php

namespace Tests\Feature\Reports;

use App\Models\Blotter;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Models\Welfare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_type' => 'admin']);
    }

    public function test_guests_cannot_access_reports(): void
    {
        $this->get('/reports')->assertRedirect('/login');
        $this->get('/reports/population')->assertRedirect('/login');
        $this->get('/reports/blotter')->assertRedirect('/login');
        $this->get('/reports/welfare')->assertRedirect('/login');
    }

    public function test_residents_are_redirected_away_from_reports(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident']);

        $this->actingAs($resident)->get('/reports')->assertRedirect(route('dashboard'));
        $this->actingAs($resident)->get('/reports/population')->assertRedirect(route('dashboard'));
    }

    public function test_hub_lists_the_three_reports(): void
    {
        $this->actingAs($this->admin)->get('/reports')
            ->assertOk()
            ->assertSee('Population Report')
            ->assertSee('Blotter Summary')
            ->assertSee('Welfare Beneficiaries');
    }

    public function test_population_report_groups_by_purok_with_age_brackets(): void
    {
        $purok = Purok::factory()->create(['name' => 'Purok Uno']);

        Resident::factory()->create([
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'sex' => 'Male', 'birth_date' => now()->subYears(70)->toDateString(), // senior
            'voter_status' => true, 'purok_id' => $purok->id,
        ]);
        Resident::factory()->create([
            'first_name' => 'Maria', 'last_name' => 'Santos',
            'sex' => 'Female', 'birth_date' => now()->subYears(5)->toDateString(), // child
            'purok_id' => $purok->id,
        ]);
        Resident::factory()->create([
            'first_name' => 'NoPurok', 'last_name' => 'Person',
            'sex' => 'Male', 'birth_date' => now()->subYears(25)->toDateString(), // youth
        ]);
        // Archived residents are excluded.
        Resident::factory()->create([
            'first_name' => 'Ghost', 'last_name' => 'Archived',
            'status' => 'Archived', 'purok_id' => $purok->id,
        ]);

        $html = $this->actingAs($this->admin)->get('/reports/population')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Purok Uno', $html);
        $this->assertStringContainsString('No Purok Assigned', $html);

        // Archived residents are excluded entirely.
        $this->assertStringNotContainsString('Ghost', $html);
        $this->assertStringNotContainsString('Archived', $html);
    }

    public function test_population_print_renders_official_document(): void
    {
        $purok = Purok::factory()->create(['name' => 'Purok Dos']);
        Resident::factory()->create([
            'first_name' => 'Senior', 'last_name' => 'Citizen',
            'birth_date' => now()->subYears(65)->toDateString(),
            'purok_id' => $purok->id, 'voter_status' => true,
        ]);

        $html = $this->actingAs($this->admin)->get('/reports/population?print=1')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Population Report', $html);
        $this->assertStringContainsString('Republic of the Philippines', $html);
        $this->assertStringContainsString('Purok Dos', $html);
        $this->assertStringContainsString('Punong Barangay', $html);
        $this->assertStringContainsString('senior citizen', $html);
        $this->assertStringNotContainsString('x-app-layout', $html);
    }

    public function test_population_respects_date_filters(): void
    {
        // The report is aggregate (counts, not names), so assert on totals:
        // unfiltered = 2 residents, filtered to the last week = 1.
        Resident::factory()->create(['created_at' => now()->subYears(2)]);
        Resident::factory()->create(['created_at' => now()->subDay()]);

        $this->actingAs($this->admin)->get('/reports/population?print=1')
            ->assertOk()
            ->assertSee('Total active residents: <b>2</b>', false);

        $this->actingAs($this->admin)->get('/reports/population?print=1&from='.now()->subWeek()->toDateString())
            ->assertOk()
            ->assertSee('Total active residents: <b>1</b>', false);
    }

    public function test_blotter_report_aggregates_by_type_and_status(): void
    {
        Blotter::factory()->create(['complaint_type' => 'Noise Complaint', 'status' => 'Open', 'complaint_date' => now()]);
        Blotter::factory()->create(['complaint_type' => 'Noise Complaint', 'status' => 'Resolved', 'complaint_date' => now()]);
        Blotter::factory()->create(['complaint_type' => 'Property Dispute', 'status' => 'Pending', 'complaint_date' => now(), 'arrest_made' => 'Yes']);

        $html = $this->actingAs($this->admin)->get('/reports/blotter')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Noise Complaint', $html);
        $this->assertStringContainsString('Property Dispute', $html);
    }

    public function test_blotter_print_renders_official_document(): void
    {
        Blotter::factory()->create(['complaint_type' => 'Physical Injury', 'status' => 'Open', 'complaint_date' => now()->subDays(2)]);

        $html = $this->actingAs($this->admin)->get('/reports/blotter?print=1')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Blotter Summary Report', $html);
        $this->assertStringContainsString('Physical Injury', $html);
        $this->assertStringContainsString('Monthly Trend', $html);
        $this->assertStringContainsString('true and correct extract', $html);
        $this->assertStringNotContainsString('x-app-layout', $html);
    }

    public function test_blotter_print_handles_empty_period(): void
    {
        $this->actingAs($this->admin)->get('/reports/blotter?print=1&from=2020-01-01&to=2020-01-31')
            ->assertOk()
            ->assertSee('No blotter cases recorded for this period');
    }

    public function test_blotter_monthly_trend_keeps_same_month_across_years_separate(): void
    {
        // Jan-2025 and Jan-2026 must land in different buckets: a month-number
        // GROUP BY would merge them into a single January column with count 2.
        Blotter::factory()->create(['complaint_type' => 'Noise Complaint', 'status' => 'Open', 'complaint_date' => '2025-01-15 10:00:00']);
        Blotter::factory()->create(['complaint_type' => 'Noise Complaint', 'status' => 'Open', 'complaint_date' => '2026-01-15 10:00:00']);

        $html = $this->actingAs($this->admin)
            ->get('/reports/blotter?from=2025-01-01&to=2026-02-28')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Jan 2025', $html);
        $this->assertStringContainsString('Jan 2026', $html);

        // Scope count assertions to the Monthly Trend table only: the
        // by-type table legitimately shows Noise Complaint = 2, so a
        // whole-page `>2</td>` check would false-positive.
        $start = strpos($html, 'Monthly Trend');
        $this->assertNotFalse($start);
        $trend = substr($html, $start);
        $end = strpos($trend, 'Most Recent Cases');
        if ($end !== false) {
            $trend = substr($trend, 0, $end);
        }

        $this->assertSame(2, substr_count($trend, '>1</td>'), 'Jan 2025 and Jan 2026 must each count 1 in the trend.');
        $this->assertStringNotContainsString('>2</td>', $trend);
    }

    public function test_welfare_report_lists_beneficiaries_and_amounts(): void
    {
        $resident = Resident::factory()->create(['first_name' => 'Ana', 'last_name' => 'Bautista']);

        Welfare::factory()->create([
            'beneficiary_id' => $resident->id,
            'assistance_type' => 'Medical',
            'program_name' => 'Ayuda Program',
            'status' => 'Released',
            'approved_amount' => 3000,
            'request_date' => now(),
        ]);
        Welfare::factory()->create([
            'assistance_type' => 'Food',
            'program_name' => 'Ayuda Program',
            'status' => 'Approved',
            'approved_amount' => 1500,
            'request_date' => now(),
        ]);

        $html = $this->actingAs($this->admin)->get('/reports/welfare')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ana', $html);
        $this->assertStringContainsString('Ayuda Program', $html);
        $this->assertStringContainsString('3,000.00', $html);
    }

    public function test_welfare_print_renders_official_document(): void
    {
        Welfare::factory()->create([
            'assistance_type' => 'Educational',
            'program_name' => ' scholarship grant',
            'status' => 'Released',
            'approved_amount' => 2000,
            'request_date' => now(),
        ]);

        $html = $this->actingAs($this->admin)->get('/reports/welfare?print=1')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Welfare Beneficiaries Report', $html);
        $this->assertStringContainsString('Educational', $html);
        $this->assertStringContainsString('Breakdown by Assistance Type', $html);
        $this->assertStringContainsString('true and correct summary', $html);
        $this->assertStringNotContainsString('x-app-layout', $html);
    }

    public function test_welfare_print_states_how_many_rows_are_shown_out_of_the_total(): void
    {
        Welfare::factory()->create([
            'beneficiary_name' => 'Cap Notice One',
            'assistance_type' => 'Food',
            'program_name' => 'Ayuda Program',
            'status' => 'Released',
            'approved_amount' => 1000,
            'request_date' => now(),
        ]);
        Welfare::factory()->create([
            'beneficiary_name' => 'Cap Notice Two',
            'assistance_type' => 'Food',
            'program_name' => 'Ayuda Program',
            'status' => 'Approved',
            'approved_amount' => 500,
            'request_date' => now(),
        ]);

        $html = $this->actingAs($this->admin)->get('/reports/welfare?print=1')
            ->assertOk()
            ->getContent();

        // The printed list is capped at 2000: the sheet must say exactly
        // what is shown so rows past the cap are never silently dropped.
        $this->assertStringContainsString('Showing 2 of 2 matching records', $html);
        $this->assertStringContainsString('Totals above cover the full period', $html);
    }

    public function test_reports_reject_malformed_or_reversed_date_ranges(): void
    {
        $this->actingAs($this->admin)
            ->get('/reports/population?from=not-a-date')
            ->assertSessionHasErrors('from');

        $this->actingAs($this->admin)
            ->get('/reports/population?from=2026-09-10&to=2026-09-01')
            ->assertSessionHasErrors('to');
    }

    public function test_report_printing_is_audited(): void
    {
        $this->actingAs($this->admin)->get('/reports/population?print=1')->assertOk();
        $this->actingAs($this->admin)->get('/reports/blotter?print=1')->assertOk();
        $this->actingAs($this->admin)->get('/reports/welfare?print=1')->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'report.printed', 'user_id' => $this->admin->id]);
        $this->assertSame(3, \App\Models\AuditLog::where('event', 'report.printed')->count());
    }
}
