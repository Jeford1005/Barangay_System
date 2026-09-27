<?php

namespace Tests\Feature\Certificates;

use App\Http\Controllers\CertificateController;
use App\Models\CertificateIssuance;
use App\Models\Document;
use App\Models\Official;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_type' => 'admin']);

        // The CLR/COR/IND catalog rows come from the migration's own seeding;
        // no need to create them here.
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'document_id' => Document::where('code', 'CLR')->first()->id,
            'resident_id' => Resident::factory()->create()->id,
            'purpose' => 'Local employment',
            'copies' => 1,
        ], $overrides);
    }

    public function test_guests_cannot_access_certificate_pages(): void
    {
        $this->get('/certificates')->assertRedirect('/login');
        $this->get('/certificates/create')->assertRedirect('/login');
        $this->post('/certificates', $this->validPayload())->assertRedirect('/login');
    }

    public function test_residents_are_redirected_away_from_certificate_pages(): void
    {
        $resident = User::factory()->create(['user_type' => 'resident']);

        $this->actingAs($resident)->get('/certificates')->assertRedirect(route('dashboard'));
        $this->actingAs($resident)->get('/certificates/create')->assertRedirect(route('dashboard'));
    }

    public function test_index_lists_issuances(): void
    {
        $issuance = CertificateIssuance::factory()->create();

        $this->actingAs($this->admin)->get('/certificates')
            ->assertOk()
            ->assertSee('Certificate Issuance')
            ->assertSee($issuance->control_number)
            ->assertSee($issuance->resident->full_name);
    }

    public function test_index_filters_by_status(): void
    {
        $issued = CertificateIssuance::factory()->create();
        $voided = CertificateIssuance::factory()->create(['status' => 'Voided']);

        $this->actingAs($this->admin)->get('/certificates?status=Voided')
            ->assertOk()
            ->assertSee($voided->control_number)
            ->assertDontSee($issued->control_number);
    }

    public function test_create_renders_the_form(): void
    {
        $purok = Purok::factory()->create(['name' => 'Purok 1']);
        Resident::factory()->create([
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'suffix' => 'Jr.',
            'purok_id' => $purok->id,
        ]);

        $this->actingAs($this->admin)->get('/certificates/create')
            ->assertOk()
            ->assertSee('Issue Certificate')
            ->assertSee('Barangay Clearance')
            ->assertSee('Certificate of Residency')
            ->assertSee('Certificate of Indigency')
            ->assertSee('Juan Santos Dela Cruz Jr. — Purok 1')
            ->assertViewHas('residents', fn ($residents) => $residents->isNotEmpty()
                && $residents->every(fn ($resident) => $resident->relationLoaded('purok')));
    }

    public function test_store_issues_a_certificate_with_control_number(): void
    {
        $document = Document::where('code', 'IND')->first(); // free of charge

        $response = $this->actingAs($this->admin)->post('/certificates', $this->validPayload([
            'document_id' => $document->id,
        ]));

        $issuance = CertificateIssuance::firstOrFail();

        $response->assertRedirect(route('certificates.print', $issuance))
            ->assertSessionHas('success');

        $this->assertSame('IND-'.now()->format('Y').'-0001', $issuance->control_number);
        $this->assertSame('Issued', $issuance->status);
        $this->assertEquals($document->fee, $issuance->fee); // defaults to catalog price
        $this->assertSame((string) $this->admin->id, (string) $issuance->issued_by);
    }

    public function test_issued_certificate_keeps_a_recipient_snapshot_for_printing(): void
    {
        $resident = Resident::factory()->create([
            'first_name' => 'Original',
            'last_name' => 'Recipient',
            'address' => 'Original Address',
        ]);
        $document = Document::where('code', 'CLR')->first();

        $this->actingAs($this->admin)->post('/certificates', $this->validPayload([
            'document_id' => $document->id,
            'resident_id' => $resident->id,
        ]));

        $issuance = CertificateIssuance::firstOrFail();
        $resident->update([
            'first_name' => 'Changed',
            'last_name' => 'Recipient',
            'address' => 'Changed Address',
        ]);

        $this->actingAs($this->admin)
            ->get(route('certificates.print', $issuance))
            ->assertOk()
            ->assertSee('Original Recipient')
            ->assertSee('Original Address')
            ->assertDontSee('Changed Address');
    }

    public function test_control_numbers_sequence_per_type_per_year(): void
    {
        $clearance = Document::where('code', 'CLR')->first();
        $year = now()->format('Y');

        // The helper computes the next number from the DB (it does not reserve):
        // first ever issuance of each type starts at 0001...
        $this->assertSame("CLR-{$year}-0001", CertificateController::getNextControlNumber('CLR'));
        $this->assertSame("COR-{$year}-0001", CertificateController::getNextControlNumber('COR'));

        // ...and continues from the highest recorded number for that type.
        CertificateIssuance::factory()->create([
            'document_id' => $clearance->id,
            'control_number' => "CLR-{$year}-0009",
        ]);

        $this->assertSame("CLR-{$year}-0010", CertificateController::getNextControlNumber('CLR'));
    }

    public function test_control_number_rejects_invalid_document_codes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CertificateController::getNextControlNumber('A code that is too long');
    }

    public function test_store_requires_the_mandatory_fields(): void
    {
        $response = $this->actingAs($this->admin)->post('/certificates', []);

        $response->assertSessionHasErrors(['document_id', 'resident_id', 'purpose', 'copies']);
        $this->assertDatabaseCount('certificate_issuances', 0);
    }

    public function test_store_rejects_non_certificate_documents(): void
    {
        $permit = Document::factory()->create([
            'code' => 'PERM',
            'document_type' => 'Permit',
            'status' => 'Active',
        ]);

        $this->actingAs($this->admin)
            ->post('/certificates', $this->validPayload(['document_id' => $permit->id]))
            ->assertSessionHasErrors('document_id');

        $this->assertDatabaseCount('certificate_issuances', 0);
    }

    public function test_store_rejects_out_of_range_copies(): void
    {
        $response = $this->actingAs($this->admin)->post('/certificates', $this->validPayload(['copies' => 6]));

        $response->assertSessionHasErrors('copies');
    }

    public function test_store_rejects_letters_in_numeric_and_foreign_key_fields(): void
    {
        $this->actingAs($this->admin)->post('/certificates', $this->validPayload([
            'document_id' => 'not-an-id',
            'resident_id' => 'not-an-id',
            'copies' => 'one',
            'fee' => 'free',
        ]))->assertSessionHasErrors(['document_id', 'resident_id', 'copies', 'fee']);

        $this->assertDatabaseCount('certificate_issuances', 0);
    }

    public function test_store_accepts_a_fee_override_for_waivers(): void
    {
        $this->actingAs($this->admin)->post('/certificates', $this->validPayload(['fee' => 0]));

        $this->assertEquals(0, CertificateIssuance::firstOrFail()->fee);
    }

    public function test_print_renders_the_clearance_template(): void
    {
        $document = Document::where('code', 'CLR')->first();
        $issuance = CertificateIssuance::factory()->create(['document_id' => $document->id]);

        $html = $this->actingAs($this->admin)->get(route('certificates.print', $issuance))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Barangay Clearance', $html);
        $this->assertStringContainsString($issuance->control_number, $html);
        $this->assertStringContainsString($issuance->resident->full_name, $html);
        $this->assertStringContainsString('good moral character', $html);
    }

    public function test_print_renders_the_residency_template(): void
    {
        $document = Document::where('code', 'COR')->first();
        $issuance = CertificateIssuance::factory()->create(['document_id' => $document->id]);

        $this->actingAs($this->admin)->get(route('certificates.print', $issuance))
            ->assertOk()
            ->assertSee('Certificate of Residency')
            ->assertSee('bona fide resident');
    }

    public function test_print_renders_the_indigency_template(): void
    {
        $document = Document::where('code', 'IND')->first();
        // Indigency certificates carry no fee (the catalog price is ₱0).
        $issuance = CertificateIssuance::factory()->create(['document_id' => $document->id, 'fee' => 0]);

        $this->actingAs($this->admin)->get(route('certificates.print', $issuance))
            ->assertOk()
            ->assertSee('Certificate of Indigency')
            ->assertSee('indigent family')
            ->assertSee('free of charge');
    }

    public function test_print_shows_the_seated_punong_barangay(): void
    {
        Official::factory()->create(['position' => 'Punong Barangay', 'status' => 'Active']);
        $issuance = CertificateIssuance::factory()->create();
        $pb = Official::where('position', 'Punong Barangay')->first();

        $this->actingAs($this->admin)->get(route('certificates.print', $issuance))
            ->assertOk()
            ->assertSee($pb->full_name)
            ->assertSee('Punong Barangay');
    }

    public function test_void_marks_a_certificate_voided(): void
    {
        $issuance = CertificateIssuance::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('certificates.void', $issuance));

        $response->assertRedirect(route('certificates.index'))
            ->assertSessionHas('success');

        $issuance->refresh();
        $this->assertSame('Voided', $issuance->status);
        $this->assertSame((string) $this->admin->id, (string) $issuance->voided_by);
        $this->assertNotNull($issuance->voided_at);
    }

    public function test_void_is_rejected_when_already_voided(): void
    {
        $issuance = CertificateIssuance::factory()->create(['status' => 'Voided']);

        $this->actingAs($this->admin)->post(route('certificates.void', $issuance))
            ->assertRedirect(route('certificates.index'))
            ->assertSessionHas('error');

        $this->assertSame('Voided', $issuance->fresh()->status);
    }

    public function test_voided_certificates_print_with_a_void_stamp(): void
    {
        $issuance = CertificateIssuance::factory()->create(['status' => 'Voided']);

        $this->actingAs($this->admin)->get(route('certificates.print', $issuance))
            ->assertOk()
            ->assertSee('VOID');
    }
}
