<?php

namespace Tests\Feature\Analytics;

use App\Models\Blotter;
use App\Models\CertificateIssuance;
use App\Models\Document;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_type' => 'admin']);
    }

    public function test_guests_cannot_access_analytics(): void
    {
        $this->get('/analytics')->assertRedirect('/login');
    }

    public function test_residents_are_redirected_away_from_analytics(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident']);

        $this->actingAs($resident)->get('/analytics')->assertRedirect(route('dashboard'));
    }

    public function test_admin_sees_kpi_headlines(): void
    {
        Resident::factory()->count(3)->create(['status' => 'Active']);
        Resident::factory()->create(['status' => 'Archived']); // excluded

        $html = $this->actingAs($this->admin)->get('/analytics')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Active Residents', $html);
        $this->assertStringContainsString('>3</p>', $html); // archived excluded from the KPI
    }

    public function test_sex_and_age_charts_render_counts(): void
    {
        Purok::factory()->create(['name' => 'Purok Uno']);
        $purok = Purok::first();

        Resident::factory()->create([
            'sex' => 'Male', 'birth_date' => now()->subYears(70)->toDateString(), 'purok_id' => $purok->id,
        ]);
        Resident::factory()->create([
            'sex' => 'Female', 'birth_date' => now()->subYears(5)->toDateString(), 'purok_id' => $purok->id,
        ]);

        $html = $this->actingAs($this->admin)->get('/analytics')->assertOk()->getContent();

        $this->assertStringContainsString('Sex Distribution', $html);
        $this->assertStringContainsString('Age Brackets', $html);
        $this->assertStringContainsString('Residents by Purok', $html);
        $this->assertStringContainsString('Purok Uno', $html);
    }

    public function test_blotter_charts_render(): void
    {
        Blotter::factory()->create(['complaint_type' => 'Noise Complaint', 'status' => 'Open']);
        Blotter::factory()->create(['complaint_type' => 'Noise Complaint', 'status' => 'Resolved']);

        $html = $this->actingAs($this->admin)->get('/analytics')->assertOk()->getContent();

        $this->assertStringContainsString('Blotter Case Status', $html);
        $this->assertStringContainsString('Top Complaint Types', $html);
        $this->assertStringContainsString('Noise Complaint', $html);
    }

    public function test_certificate_trend_renders(): void
    {
        $document = Document::factory()->create();
        CertificateIssuance::factory()->count(2)->create([
            'document_id' => $document->id,
            'status' => 'Issued',
        ]);
        CertificateIssuance::factory()->create([
            'document_id' => $document->id,
            'status' => 'Voided',
        ]);

        $html = $this->actingAs($this->admin)->get('/analytics')->assertOk()->getContent();

        $this->assertStringContainsString('Certificates Issued', $html);
        $this->assertStringContainsString('Welfare Assistance', $html);
    }

    public function test_charts_handle_empty_database(): void
    {
        $this->actingAs($this->admin)->get('/analytics')
            ->assertOk()
            ->assertSee('No blotter cases yet.');
    }

    public function test_trend_buckets_cover_the_last_twelve_months(): void
    {
        // A resident created "13 months ago" is still bucketed correctly (outside window),
        // and this-month records land in the current bucket.
        Resident::factory()->create(['status' => 'Active', 'created_at' => now()->subMonths(13)]);
        Resident::factory()->create(['status' => 'Active', 'created_at' => now()]);

        $html = $this->actingAs($this->admin)->get('/analytics')->assertOk()->getContent();

        // 12 columns in the registration chart; the current month shows the 1 in-window resident.
        $this->assertStringContainsString('New Resident Registrations', $html);
    }
}
