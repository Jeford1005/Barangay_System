<?php

namespace Tests\Feature\Console;

use App\Models\Blotter;
use App\Models\CertificateIssuance;
use App\Models\CertificateRequest;
use App\Models\Document;
use App\Models\Household;
use App\Models\Resident;
use App\Models\User;
use App\Models\Welfare;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DataQualityAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_passes_for_consistent_data_without_writing(): void
    {
        $before = $this->databaseSnapshot();

        [$exitCode, $output] = $this->runAudit();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Barangay data quality audit (read-only)', $output);
        $this->assertStringContainsString('Orphaned foreign keys', $output);
        $this->assertStringContainsString('Duplicate resident user_id links', $output);
        $this->assertStringContainsString('Household head inconsistencies', $output);
        $this->assertStringContainsString('Invalid enum values', $output);
        $this->assertStringContainsString('Invalid phone/email/ZIP values', $output);
        $this->assertStringContainsString('Duplicate household/document codes', $output);
        $this->assertStringContainsString('Certificate requests linked to inactive residents', $output);
        $this->assertStringContainsString('PASS No data integrity issues found. No data was changed.', $output);
        $this->assertSame($before, $this->databaseSnapshot());
    }

    public function test_command_can_emit_machine_readable_json(): void
    {
        $this->artisan('data:quality-audit --format=json')
            ->expectsOutputToContain('"ok": true')
            ->assertExitCode(0);
    }

    public function test_command_reports_relationship_and_certificate_issues_without_writing(): void
    {
        $user = User::factory()->resident()->create(['status' => 'approved']);
        $firstHousehold = Household::factory()->create();
        $secondHousehold = Household::factory()->create();
        $selectedHead = Resident::factory()->create([
            'user_id' => $user->id,
            'household_id' => $secondHousehold->id,
            'is_household_head' => false,
        ]);
        Resident::factory()->create(['user_id' => $user->id]);
        $headWithoutHousehold = Resident::factory()->create([
            'is_household_head' => true,
            'household_id' => null,
        ]);
        $archivedResident = Resident::factory()->create(['status' => 'Archived']);
        $softDeletedResident = Resident::factory()->create();
        $document = Document::query()->where('code', 'CLR')->firstOrFail();

        $firstHousehold->update(['head_of_household_id' => $selectedHead->id]);
        $archivedRequest = CertificateRequest::create([
            'resident_id' => $archivedResident->id,
            'document_id' => $document->id,
            'purpose' => 'Archived resident audit fixture',
            'copies' => 1,
            'status' => 'Pending',
        ]);
        $deletedRequest = CertificateRequest::create([
            'resident_id' => $softDeletedResident->id,
            'document_id' => $document->id,
            'purpose' => 'Soft-deleted resident audit fixture',
            'copies' => 1,
            'status' => 'Pending',
        ]);
        $softDeletedResident->delete();
        $before = $this->databaseSnapshot();

        [$exitCode, $output] = $this->runAudit();

        $this->assertSame(1, $exitCode, $output);
        $this->assertStringContainsString("residents.user_id: user id={$user->id} is linked by resident ids", $output);
        $this->assertStringContainsString("household id={$firstHousehold->id} selects resident id={$selectedHead->id}", $output);
        $this->assertStringContainsString('the resident is not marked as a household head', $output);
        $this->assertStringContainsString("resident id={$headWithoutHousehold->id} is marked as a household head without a household", $output);
        $this->assertStringContainsString("request id={$archivedRequest->id} links resident id={$archivedResident->id}, but status is \"Archived\"", $output);
        $this->assertStringContainsString("request id={$deletedRequest->id} links resident id={$softDeletedResident->id}, but resident is soft-deleted", $output);
        $this->assertStringContainsString('No data was changed.', $output);
        $this->assertSame($before, $this->databaseSnapshot());
    }

    public function test_command_reports_invalid_values_and_duplicate_codes_without_writing(): void
    {
        $user = User::factory()->create(['email' => 'valid-user@example.com']);
        $resident = Resident::factory()->create();
        $household = Household::factory()->create([
            'household_code' => 'AUDIT-HH-001',
            'house_type' => 'Single',
        ]);
        $blotter = Blotter::factory()->create();
        $welfare = Welfare::factory()->create();
        $document = Document::query()->where('code', 'CLR')->firstOrFail();
        $request = CertificateRequest::create([
            'resident_id' => $resident->id,
            'document_id' => $document->id,
            'purpose' => 'Invalid value audit fixture',
            'copies' => 1,
            'status' => 'Pending',
        ]);
        $issuance = CertificateIssuance::create([
            'control_number' => 'AUDIT-CERT-001',
            'document_id' => $document->id,
            'resident_id' => $resident->id,
            'purpose' => 'Invalid value audit fixture',
            'copies' => 1,
            'fee' => 0,
            'status' => 'Issued',
        ]);

        DB::statement('PRAGMA ignore_check_constraints = ON');

        try {
            DB::table('users')->where('id', $user->id)->update([
                'email' => 'not-an-email',
                'user_type' => 'super-admin',
            ]);
            DB::table('residents')->where('id', $resident->id)->update([
                'sex' => 'Unknown',
                'phone_number' => 'phone-number',
                'email' => 'resident-email-invalid',
            ]);
            DB::table('households')->where('id', $household->id)->update([
                'house_type' => 'Castle',
                'zip_code' => '12A45',
            ]);
            DB::table('blotter')->where('id', $blotter->id)->update([
                'status' => 'Unknown',
                'complainant_phone' => 'letters',
            ]);
            DB::table('welfare')->where('id', $welfare->id)->update([
                'assistance_type' => 'Cash',
                'beneficiary_phone' => '123 letters',
            ]);
            DB::table('documents')->where('id', $document->id)->update([
                'document_type' => 'Poster',
                'status' => 'Retired',
            ]);
            DB::table('certificate_requests')->where('id', $request->id)->update(['status' => 'Waiting']);
            DB::table('certificate_issuances')->where('id', $issuance->id)->update(['status' => 'Lost']);
        } finally {
            DB::statement('PRAGMA ignore_check_constraints = OFF');
        }

        Schema::table('households', function (Blueprint $table): void {
            $table->dropUnique(['household_code']);
        });
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropUnique(['code']);
        });
        Household::factory()->create(['household_code' => $household->household_code]);
        Document::factory()->create(['code' => $document->code]);
        $before = $this->databaseSnapshot();

        [$exitCode, $output] = $this->runAudit();

        $this->assertSame(1, $exitCode, $output);
        $this->assertStringContainsString('users.user_type: user id='.$user->id.' has invalid value "super-admin"', $output);
        $this->assertStringContainsString('users.email: user id='.$user->id.' has invalid email value "not-an-email"', $output);
        $this->assertStringContainsString('residents.sex: resident id='.$resident->id.' has invalid value "Unknown"', $output);
        $this->assertStringContainsString('residents.phone_number: resident id='.$resident->id.' has invalid phone value "phone-number"', $output);
        $this->assertStringContainsString('residents.email: resident id='.$resident->id.' has invalid email value "resident-email-invalid"', $output);
        $this->assertStringContainsString('households.house_type: household id='.$household->id.' has invalid value "Castle"', $output);
        $this->assertStringContainsString('households.zip_code: household id='.$household->id.' has invalid ZIP value "12A45"', $output);
        $this->assertStringContainsString('blotter.status: blotter id='.$blotter->id.' has invalid value "Unknown"', $output);
        $this->assertStringContainsString('welfare.assistance_type: welfare id='.$welfare->id.' has invalid value "Cash"', $output);
        $this->assertStringContainsString('documents.document_type: document id='.$document->id.' has invalid value "Poster"', $output);
        $this->assertStringContainsString('certificate_requests.status: certificate_request id='.$request->id.' has invalid value "Waiting"', $output);
        $this->assertStringContainsString('certificate_issuances.status: certificate_issuance id='.$issuance->id.' has invalid value "Lost"', $output);
        $this->assertStringContainsString('households.household_code: code "AUDIT-HH-001" is used by household ids', $output);
        $this->assertStringContainsString('documents.code: code "CLR" is used by document ids', $output);
        $this->assertSame($before, $this->databaseSnapshot());
    }

    public function test_command_reports_orphaned_foreign_keys_without_writing(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create();
        $resident = Resident::factory()->create();
        $blotter = Blotter::factory()->create();
        $welfare = Welfare::factory()->create();
        $document = Document::query()->where('code', 'CLR')->firstOrFail();
        $request = CertificateRequest::create([
            'resident_id' => $resident->id,
            'document_id' => $document->id,
            'purpose' => 'Orphan audit fixture',
            'copies' => 1,
            'status' => 'Pending',
        ]);
        $issuance = CertificateIssuance::create([
            'control_number' => 'ORPHAN-CERT-001',
            'document_id' => $document->id,
            'resident_id' => $resident->id,
            'purpose' => 'Orphan audit fixture',
            'copies' => 1,
            'fee' => 0,
            'status' => 'Issued',
        ]);

        // Deferred constraints let this focused test model rows that could only
        // exist in a database migrated or restored with broken foreign keys.
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('users')->where('id', $user->id)->update(['reviewed_by' => 900001]);
        DB::table('residents')->where('id', $resident->id)->update(['user_id' => 900002]);
        DB::table('households')->where('id', $household->id)->update(['head_of_household_id' => 900003]);
        DB::table('blotter')->where('id', $blotter->id)->update(['accused_id' => 900004]);
        DB::table('welfare')->where('id', $welfare->id)->update(['beneficiary_id' => 900005]);
        DB::table('documents')->where('id', $document->id)->update([
            'created_by' => 900008,
            'updated_by' => 900009,
        ]);
        DB::table('certificate_requests')->where('id', $request->id)->update(['issuance_id' => 900006]);
        DB::table('certificate_issuances')->where('id', $issuance->id)->update(['issued_by' => 900007]);
        $before = $this->databaseSnapshot();

        [$exitCode, $output] = $this->runAudit();

        $this->assertSame(1, $exitCode, $output);
        $this->assertStringContainsString("users.reviewed_by: user id={$user->id} references missing reviewer id=900001", $output);
        $this->assertStringContainsString("residents.user_id: resident id={$resident->id} references missing user id=900002", $output);
        $this->assertStringContainsString("households.head_of_household_id: household id={$household->id} references missing head resident id=900003", $output);
        $this->assertStringContainsString("blotter.accused_id: blotter id={$blotter->id} references missing accused resident id=900004", $output);
        $this->assertStringContainsString("welfare.beneficiary_id: welfare id={$welfare->id} references missing beneficiary id=900005", $output);
        $this->assertStringContainsString("documents.created_by: document id={$document->id} references missing creator id=900008", $output);
        $this->assertStringContainsString("documents.updated_by: document id={$document->id} references missing updater id=900009", $output);
        $this->assertStringContainsString("certificate_requests.issuance_id: certificate_request id={$request->id} references missing certificate issuance id=900006", $output);
        $this->assertStringContainsString("certificate_issuances.issued_by: certificate_issuance id={$issuance->id} references missing issuer id=900007", $output);
        $this->assertSame($before, $this->databaseSnapshot());
    }

    /**
     * @return array{int, string}
     */
    private function runAudit(): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $exitCode = Artisan::call('data:quality-audit');
        $output = Artisan::output();
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $mutatingQueries = collect($queries)
            ->filter(fn (array $query): bool => preg_match(
                '/^\s*(alter|create|delete|drop|insert|merge|pragma|replace|truncate|update|vacuum)\b/i',
                $query['query'],
            ) === 1)
            ->pluck('query')
            ->values()
            ->all();

        $this->assertSame([], $mutatingQueries, 'The data quality audit must execute read-only SQL.');

        return [$exitCode, $output];
    }

    private function databaseSnapshot(): array
    {
        $snapshot = [];

        foreach ([
            'users',
            'residents',
            'households',
            'blotter',
            'welfare',
            'documents',
            'certificate_requests',
            'certificate_issuances',
        ] as $table) {
            $snapshot[$table] = DB::table($table)
                ->orderBy('id')
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all();
        }

        return $snapshot;
    }
}
