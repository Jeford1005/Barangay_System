<?php

namespace Tests\Feature\Exports;

use App\Models\AuditLog;
use App\Models\Blotter;
use App\Models\CertificateIssuance;
use App\Models\Document;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Models\Welfare;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AdminExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // The route file is intentionally not loaded by routes/web.php in this
        // slice. Register it in the test application so the feature tests are
        // executable before the primary integration adds the one-line require.
        if (! app('router')->getRoutes()->getByName('admin.exports.residents')) {
            require base_path('routes/admin-exports.php');
        }

        $this->admin = User::factory()->create([
            'user_type' => 'admin',
            'email' => 'exporter@barangay.local',
        ]);
    }

    public function test_each_export_is_a_streamed_utf8_csv_with_a_header_row(): void
    {
        foreach ([
            'residents' => 'residents',
            'households' => 'households',
            'blotter' => 'blotter',
            'welfare' => 'welfare',
            'certificates' => 'certificate-issuances',
        ] as $dataset => $filenamePrefix) {
            $response = $this->actingAs($this->admin)
                ->get('/admin/exports/'.$dataset)
                ->assertOk()
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->assertHeader('X-Accel-Buffering', 'no');

            $this->assertInstanceOf(
                StreamedResponse::class,
                $response->baseResponse,
            );

            $disposition = (string) $response->headers->get('Content-Disposition');
            $this->assertMatchesRegularExpression(
                '/^attachment; filename="'.preg_quote($filenamePrefix, '/').'-\d{4}-\d{2}-\d{2}\.csv"(; filename\*=UTF-8\'\'.+)?$/',
                $disposition,
            );

            $content = $response->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
            $this->assertSame('ID', $this->firstCsvRow($response)[0]);
        }
    }

    public function test_streaming_visits_multiple_bounded_chunks_without_dropping_records(): void
    {
        $count = ExportService::CHUNK_SIZE + 2;
        for ($sequence = 1; $sequence <= $count; $sequence++) {
            Resident::create([
                'first_name' => 'Stream',
                'last_name' => 'Record'.$sequence,
                'sex' => 'Other',
                'civil_status' => 'Single',
                'status' => 'Active',
            ]);
        }

        $rows = $this->dataRows($this->export('residents'));

        $this->assertCount($count, $rows);
        $this->assertCount($count, array_unique(array_column($rows, 0)));
    }

    public function test_resident_export_uses_the_index_search_and_filters(): void
    {
        $purokA = Purok::factory()->create(['name' => 'Export Purok A']);
        $purokB = Purok::factory()->create(['name' => 'Export Purok B']);
        $householdA = Household::factory()->create([
            'household_code' => 'HH-EXPORT-A',
            'purok_id' => $purokA->id,
        ]);
        $householdB = Household::factory()->create([
            'household_code' => 'HH-EXPORT-B',
            'purok_id' => $purokB->id,
        ]);

        $matching = Resident::factory()->create([
            'first_name' => 'ExportMatch',
            'last_name' => 'Resident',
            'purok_id' => $purokA->id,
            'household_id' => $householdA->id,
            'status' => 'Active',
        ]);
        Resident::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'Resident',
            'purok_id' => $purokB->id,
            'household_id' => $householdB->id,
            'status' => 'Active',
        ]);

        $response = $this->export('residents', [
            'search' => 'ExportMatch',
            'purok_id' => $purokA->id,
            'household_id' => $householdA->id,
            'status' => 'Active',
        ]);

        $rows = $this->dataRows($response);
        $this->assertCount(1, $rows);
        $this->assertSame((string) $matching->id, $rows[0][0]);
        $this->assertStringNotContainsString('Other', $response->streamedContent());
    }

    public function test_household_blotter_welfare_and_certificate_exports_keep_their_module_filters(): void
    {
        $purok = Purok::factory()->create(['name' => 'Filtered Export Purok']);
        $matchingHousehold = Household::factory()->create([
            'household_code' => 'HH-FILTER-MATCH',
            'purok_id' => $purok->id,
            'street' => 'Export Street',
        ]);
        Household::factory()->create([
            'household_code' => 'HH-FILTER-OTHER',
            'purok_id' => null,
            'street' => 'Other Street',
        ]);

        $householdResponse = $this->export('households', [
            // A code prefix: household_code is prefix-matched (sargable) while
            // street/barangay stay contains-matched, so this hits MATCH under
            // either semantic and still proves the purok filter combines.
            'search' => 'HH-FILTER',
            'purok_id' => $purok->id,
        ]);
        $this->assertSame([(string) $matchingHousehold->id], array_column($this->dataRows($householdResponse), 0));

        $matchingBlotter = Blotter::factory()->create([
            'case_number' => 'BLTR-EXPORT-MATCH',
            'complainant_name' => 'Blotter Match',
            'complaint_type' => 'Noise Complaint',
            'status' => 'Resolved',
        ]);
        Blotter::factory()->create([
            'case_number' => 'BLTR-EXPORT-OTHER',
            'complainant_name' => 'Blotter Other',
            'complaint_type' => 'Theft',
            'status' => 'Open',
        ]);
        $blotterResponse = $this->export('blotter', [
            'search' => 'Blotter Match',
            'status' => 'Resolved',
        ]);
        $this->assertSame([(string) $matchingBlotter->id], array_column($this->dataRows($blotterResponse), 0));

        $matchingWelfare = Welfare::factory()->create([
            'beneficiary_name' => 'Welfare Match',
            'program_name' => 'Export Program',
            'assistance_type' => 'Medical',
            'status' => 'Approved',
        ]);
        Welfare::factory()->create([
            'beneficiary_name' => 'Welfare Other',
            'program_name' => 'Other Program',
            'assistance_type' => 'Food',
            'status' => 'Requested',
        ]);
        $welfareResponse = $this->export('welfare', [
            'search' => 'Welfare Match',
            'assistance_type' => 'Medical',
            'status' => 'Approved',
        ]);
        $this->assertSame([(string) $matchingWelfare->id], array_column($this->dataRows($welfareResponse), 0));

        $document = Document::where('code', 'CLR')->firstOrFail();
        $otherDocument = Document::where('code', 'COR')->firstOrFail();
        $matchingResident = Resident::factory()->create([
            'first_name' => 'CertificateMatch',
            'last_name' => 'Resident',
        ]);
        $otherResident = Resident::factory()->create([
            'first_name' => 'CertificateOther',
            'last_name' => 'Resident',
        ]);
        $matchingCertificate = CertificateIssuance::factory()->create([
            'control_number' => 'CLR-EXPORT-MATCH',
            'document_id' => $document->id,
            'resident_id' => $matchingResident->id,
            'status' => 'Issued',
        ]);
        CertificateIssuance::factory()->create([
            'control_number' => 'COR-EXPORT-OTHER',
            'document_id' => $otherDocument->id,
            'resident_id' => $otherResident->id,
            'status' => 'Voided',
        ]);
        $certificateResponse = $this->export('certificates', [
            'search' => 'CertificateMatch',
            'document_id' => $document->id,
            'status' => 'Issued',
        ]);
        $this->assertSame([(string) $matchingCertificate->id], array_column($this->dataRows($certificateResponse), 0));
    }

    public function test_formula_like_record_values_are_exported_as_literal_text(): void
    {
        Resident::factory()->create([
            'first_name' => '=HYPERLINK("https://evil.test","click")',
            'last_name' => '+SUM(1,1)',
            'address' => '@unsafe.example',
        ]);

        $rows = $this->dataRows($this->export('residents'));
        $this->assertCount(1, $rows);
        $this->assertSame("'=HYPERLINK(\"https://evil.test\",\"click\")", $rows[0][1]);
        $this->assertSame("'+SUM(1,1)", $rows[0][3]);
        $this->assertSame("'@unsafe.example", $rows[0][18]);
    }

    public function test_exports_are_audited_with_the_actor_and_effective_filters(): void
    {
        $this->export('residents', [
            'search' => str_repeat('x', ExportService::MAX_SEARCH_LENGTH + 50),
            'status' => 'Active',
        ], [
            'REMOTE_ADDR' => '203.0.113.44',
            'HTTP_USER_AGENT' => 'Export test agent',
        ]);

        $log = AuditLog::where('event', 'export.residents')->firstOrFail();

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($this->admin->email, $log->user_email);
        $this->assertSame('203.0.113.44', $log->ip_address);
        $this->assertSame('Export test agent', $log->user_agent);
        $this->assertSame('csv', $log->properties['format']);
        $this->assertSame('residents', $log->properties['type']);
        $this->assertSame('residents', $log->properties['resource']);
        $this->assertSame(ExportService::MAX_SEARCH_LENGTH, mb_strlen($log->properties['filters']['search']));
        $this->assertSame('Active', $log->properties['filters']['status']);
    }

    public function test_guests_and_residents_cannot_export_records(): void
    {
        $this->get('/admin/exports/residents')->assertRedirect(route('login'));

        $resident = User::factory()->resident()->create();

        $this->actingAs($resident)
            ->get('/admin/exports/residents')
            ->assertRedirect(route('dashboard'));
    }

    public function test_formula_neutralization_handles_all_common_formula_prefixes(): void
    {
        $this->assertSame("'=1+1", ExportService::neutralizeSpreadsheetFormula('=1+1'));
        $this->assertSame("'+1", ExportService::neutralizeSpreadsheetFormula('+1'));
        $this->assertSame("'-cmd", ExportService::neutralizeSpreadsheetFormula('-cmd'));
        $this->assertSame("'@SUM(1,1)", ExportService::neutralizeSpreadsheetFormula('@SUM(1,1)'));
        $this->assertSame("'-12.50", ExportService::neutralizeSpreadsheetFormula('-12.50'));
    }

    private function export(string $dataset, array $query = [], array $server = []): TestResponse
    {
        $url = '/admin/exports/'.$dataset;
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $this->actingAs($this->admin)->get($url, $server);
    }

    /** @return list<list<string>> */
    private function firstCsvRow(TestResponse $response): array
    {
        $content = $response->streamedContent();
        $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
        $line = strtok($content, "\r\n");

        return str_getcsv((string) $line);
    }

    /** @return list<list<string>> */
    private function dataRows(TestResponse $response): array
    {
        $content = $response->streamedContent();
        $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $rows = array_map(static fn (string $line): array => str_getcsv($line), $lines);
        $dataRows = array_slice($rows, 1);

        return array_values(array_filter(
            $dataRows,
            static fn (array $row): bool => collect($row)->contains(static fn (mixed $value): bool => $value !== null && $value !== ''),
        ));
    }
}
