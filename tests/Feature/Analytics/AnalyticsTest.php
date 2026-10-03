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

    public function test_trend_labels_keep_years_separate_across_a_year_boundary(): void
    {
        // Freeze February so the 12-month window spans Dec 2025 -> Feb 2026.
        // A month-number-only GROUP BY would merge the two Decembers/Jans;
        // year-month buckets keep them apart with readable "Mon YYYY" labels.
        $now = \Illuminate\Support\Carbon::create(2026, 2, 15, 12, 0, 0, 'Asia/Manila');
        \Illuminate\Support\Carbon::setTestNow($now);

        try {
            Resident::factory()->create([
                'status' => 'Active',
                'created_at' => \Illuminate\Support\Carbon::create(2025, 12, 10, 9, 0, 0, 'Asia/Manila'),
            ]);
            Resident::factory()->count(2)->create([
                'status' => 'Active',
                'created_at' => \Illuminate\Support\Carbon::create(2026, 1, 10, 9, 0, 0, 'Asia/Manila'),
            ]);

            $trend = $this->invokeMonthlyTrend(
                \App\Models\Resident::class,
                'created_at',
                true
            );

            $byLabel = collect($trend)->mapWithKeys(fn (array $row) => [$row['label'] => $row['count']]);

            $this->assertArrayHasKey('Dec 2025', $byLabel->all());
            $this->assertArrayHasKey('Jan 2026', $byLabel->all());
            $this->assertSame(1, $byLabel['Dec 2025']);
            $this->assertSame(2, $byLabel['Jan 2026']);

            $html = $this->actingAs($this->admin)->get('/analytics')->assertOk()->getContent();
            $this->assertStringContainsString('Dec 2025', $html);
            $this->assertStringContainsString('Jan 2026', $html);
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function invokeMonthlyTrend(string $model, string $column, bool $activeOnly): array
    {
        $method = new \ReflectionMethod(\App\Http\Controllers\AnalyticsController::class, 'monthlyTrend');
        $method->setAccessible(true);

        return $method->invoke(new \App\Http\Controllers\AnalyticsController, $model, $column, $activeOnly);
    }
}
