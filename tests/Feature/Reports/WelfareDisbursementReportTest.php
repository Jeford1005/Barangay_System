<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Models\Welfare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR22 — welfare disbursement report with per-program totals.
 *
 * docs/srs/requirements-validation-checklist.md still marks FR22 as
 * "planned next" with no implementation, but ReportController::welfare()
 * already aggregates committed money per program ($byProgram, Approved +
 * Released only) on both the screen and print views. This suite pins that
 * behaviour so the checklist row can be closed as validated: committed
 * totals per program, with Requested/Denied rows excluded from the money
 * even when they carry a nonzero approved_amount.
 */
class WelfareDisbursementReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_screen_report_shows_committed_totals_per_program(): void
    {
        Welfare::factory()->approved()->create([
            'program_name' => 'FR22 Medical Aid',
            'approved_amount' => 3000,
            'request_date' => now()->toDateString(),
        ]);
        Welfare::factory()->released()->create([
            'program_name' => 'FR22 Medical Aid',
            'approved_amount' => 2000,
            'request_date' => now()->toDateString(),
        ]);
        Welfare::factory()->released()->create([
            'program_name' => 'FR22 Food Aid',
            'approved_amount' => 1500,
            'request_date' => now()->toDateString(),
        ]);
        // Decoys: money that must never book as disbursed.
        Welfare::factory()->create([
            'program_name' => 'FR22 Pending Aid',
            'status' => 'Requested',
            'approved_amount' => 7777,
            'request_date' => now()->toDateString(),
        ]);
        Welfare::factory()->create([
            'program_name' => 'FR22 Denied Aid',
            'status' => 'Denied',
            'approved_amount' => 0,
            'request_date' => now()->toDateString(),
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('reports.welfare'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('By Program', $html);

        // Scope assertions to the By Program table: the beneficiary list
        // below legitimately shows each row's raw approved_amount, so only
        // this section proves the aggregation excludes uncommitted money.
        $section = $this->programSection($html);

        $this->assertStringContainsString('FR22 Medical Aid', $section);
        $this->assertStringContainsString('FR22 Food Aid', $section);
        $this->assertStringContainsString(number_format(5000, 2), $section);
        $this->assertStringContainsString(number_format(1500, 2), $section);

        // The Requested decoy books zero despite its 7777 approved_amount.
        $this->assertStringContainsString('FR22 Pending Aid', $section);
        $this->assertStringNotContainsString('7,777.00', $section);
    }

    public function test_print_report_carries_the_same_per_program_breakdown(): void
    {
        Welfare::factory()->approved()->create([
            'program_name' => 'FR22 Print Aid',
            'approved_amount' => 4200,
            'request_date' => now()->toDateString(),
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('reports.welfare', ['print' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Breakdown by Program', $html);
        $this->assertStringContainsString('FR22 Print Aid', $html);
        $this->assertStringContainsString(number_format(4200, 2), $html);
    }

    /**
     * The slice of the screen report between the By Program heading and the
     * beneficiary list, so money assertions target the aggregation only.
     */
    private function programSection(string $html): string
    {
        $start = strpos($html, 'By Program');
        $end = strpos($html, 'Beneficiary List');

        $this->assertNotFalse($start, 'The By Program breakdown is missing.');
        $this->assertNotFalse($end, 'The beneficiary list is missing.');

        return substr($html, $start, $end - $start);
    }
}
