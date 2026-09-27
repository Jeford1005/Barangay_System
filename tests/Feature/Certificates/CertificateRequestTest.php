<?php

namespace Tests\Feature\Certificates;

use App\Models\CertificateIssuance;
use App\Models\CertificateRequest;
use App\Models\Document;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\CertificateRequestDecisionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CertificateRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $residentUser;

    private Resident $resident;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_type' => 'admin']);

        // Resident account with a linked resident profile.
        $this->residentUser = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        $this->resident = Resident::factory()->create([
            'user_id' => $this->residentUser->id,
            'purok_id' => Purok::factory(),
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'document_id' => Document::where('code', 'CLR')->first()->id,
            'purpose' => 'Local employment',
            'copies' => 1,
        ], $overrides);
    }

    // ---------- Access control ----------

    public function test_guests_cannot_access_request_pages(): void
    {
        $this->get('/my/requests')->assertRedirect('/login');
        $this->post('/my/requests', $this->validPayload())->assertRedirect('/login');
    }

    public function test_admins_are_redirected_away_from_the_resident_queue(): void
    {
        $this->actingAs($this->admin)->get('/my/requests')->assertRedirect(route('dashboard'));
    }

    public function test_residents_cannot_access_the_admin_queue(): void
    {
        $this->actingAs($this->residentUser)->get('/admin/certificate-requests')
            ->assertRedirect(route('dashboard'));
    }

    // ---------- Resident side ----------

    public function test_resident_can_submit_a_request(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload())
            ->assertRedirect(route('resident.requests'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('certificate_requests', [
            'resident_id' => $this->resident->id,
            'document_id' => Document::where('code', 'CLR')->first()->id,
            'purpose' => 'Local employment',
            'copies' => 1,
            'status' => 'Pending',
        ]);
    }

    public function test_archived_resident_cannot_submit_a_certificate_request(): void
    {
        $this->resident->update(['status' => 'Archived']);

        $this->actingAs($this->residentUser)
            ->post('/my/requests', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('certificate_requests', 0);
    }

    public function test_resident_can_view_only_their_own_approved_certificate(): void
    {
        $requestModel = CertificateRequest::create([
            'resident_id' => $this->resident->id,
            'document_id' => Document::where('code', 'CLR')->first()->id,
            'purpose' => 'Local employment',
            'copies' => 1,
            'status' => 'Pending',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.certificate-requests.approve', $requestModel))
            ->assertRedirect();

        $this->actingAs($this->residentUser)
            ->get(route('resident.requests.certificate', $requestModel))
            ->assertOk()
            ->assertSee($requestModel->fresh()->issuance->control_number);

        $other = User::factory()->resident()->create();
        $otherResident = Resident::factory()->create(['user_id' => $other->id]);
        $this->actingAs($other)
            ->get(route('resident.requests.certificate', $requestModel))
            ->assertForbidden();
    }

    public function test_resident_cannot_submit_two_pending_requests_for_the_same_type(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload())
            ->assertSessionHas('error');

        $this->assertSame(1, CertificateRequest::count());
    }

    public function test_resident_can_submit_for_a_different_type_while_one_is_pending(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());

        $indigency = Document::where('code', 'IND')->first()->id;
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload([
            'document_id' => $indigency,
            'purpose' => 'Medical assistance',
        ]))->assertSessionHas('success');

        $this->assertSame(2, CertificateRequest::count());
    }

    public function test_resident_can_request_again_after_the_first_was_reviewed(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());
        CertificateRequest::firstOrFail()->update(['status' => 'Approved']);

        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload())
            ->assertSessionHas('success');

        $this->assertSame(2, CertificateRequest::count());
    }

    public function test_resident_can_cancel_their_own_pending_request(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());
        $request = CertificateRequest::firstOrFail();

        $this->actingAs($this->residentUser)->post('/my/requests/'.$request->id.'/cancel')
            ->assertSessionHas('success');

        $this->assertSame('Cancelled', $request->fresh()->status);
    }

    public function test_resident_cannot_cancel_someone_elses_request(): void
    {
        $otherUser = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        $otherResident = Resident::factory()->create(['user_id' => $otherUser->id]);

        $request = CertificateRequest::create([
            'resident_id' => $otherResident->id,
            'document_id' => Document::where('code', 'CLR')->first()->id,
            'purpose' => 'Employment',
            'copies' => 1,
            'status' => 'Pending',
        ]);

        $this->actingAs($this->residentUser)->post('/my/requests/'.$request->id.'/cancel')
            ->assertForbidden();

        $this->assertSame('Pending', $request->fresh()->status);
    }

    public function test_resident_request_rejects_letters_in_numeric_and_foreign_key_fields(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload([
            'document_id' => 'not-an-id',
            'copies' => 'one',
        ]))->assertSessionHasErrors(['document_id', 'copies']);

        $this->assertDatabaseCount('certificate_requests', 0);
    }

    public function test_store_rejects_inactive_document_types(): void
    {
        $inactive = Document::factory()->create(['code' => 'OFF', 'status' => 'Inactive']);

        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload([
            'document_id' => $inactive->id,
        ]))->assertSessionHasErrors('document_id');
    }

    // ---------- Admin side ----------

    public function test_admin_queue_lists_requests_with_pending_badge_count(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());

        $html = $this->actingAs($this->admin)->get('/admin/certificate-requests')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($this->resident->full_name, $html);
        $this->assertStringContainsString('Barangay Clearance', $html);
        $this->assertStringContainsString('1 pending review', $html);
    }

    public function test_approve_creates_an_issuance_and_notifies_the_resident(): void
    {
        Notification::fake();

        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());
        $request = CertificateRequest::firstOrFail();

        $response = $this->actingAs($this->admin)->post(
            route('admin.certificate-requests.approve', $request),
        );

        $issuance = CertificateIssuance::firstOrFail();

        $response->assertRedirect(route('certificates.print', $issuance))
            ->assertSessionHas('success');

        $request->refresh();
        $this->assertSame('Approved', $request->status);
        $this->assertSame($issuance->id, $request->issuance_id);
        $this->assertSame((string) $this->admin->id, (string) $request->reviewed_by);
        $this->assertSame('CLR-'.now()->format('Y').'-0001', $issuance->control_number);
        $this->assertEquals(Document::where('code', 'CLR')->first()->fee, $issuance->fee);

        Notification::assertSentTo(
            $this->residentUser,
            CertificateRequestDecisionNotification::class,
            fn ($n) => $n->approved === true,
        );

        $this->assertDatabaseHas('audit_logs', ['event' => 'certificate.request_approved']);
    }

    public function test_approve_accepts_a_fee_override(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());
        $request = CertificateRequest::firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.certificate-requests.approve', $request), ['fee' => 0]);

        $this->assertEquals(0, CertificateIssuance::firstOrFail()->fee);
    }

    public function test_approve_rejects_letters_in_the_fee_override(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());
        $request = CertificateRequest::firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.certificate-requests.approve', $request), ['fee' => 'free'])
            ->assertSessionHasErrors('fee');

        $this->assertDatabaseCount('certificate_issuances', 0);
    }

    public function test_reject_records_the_reason_and_notifies_the_resident(): void
    {
        Notification::fake();

        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());
        $request = CertificateRequest::firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.certificate-requests.reject', $request), [
            'rejection_reason' => 'Requires a barangay hearing first',
        ])->assertRedirect(route('admin.certificate-requests.index'))
            ->assertSessionHas('success');

        $request->refresh();
        $this->assertSame('Rejected', $request->status);
        $this->assertSame('Requires a barangay hearing first', $request->rejection_reason);
        $this->assertNull($request->issuance_id);
        $this->assertSame(0, CertificateIssuance::count());

        Notification::assertSentTo(
            $this->residentUser,
            CertificateRequestDecisionNotification::class,
            fn ($n) => $n->approved === false,
        );

        $this->assertDatabaseHas('audit_logs', ['event' => 'certificate.request_rejected']);
    }

    public function test_reject_requires_a_reason(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());
        $request = CertificateRequest::firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.certificate-requests.reject', $request), [])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame('Pending', $request->fresh()->status);
    }

    public function test_already_reviewed_requests_cannot_be_reviewed_again(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());
        $request = CertificateRequest::firstOrFail();
        $request->update(['status' => 'Approved']);

        $this->actingAs($this->admin)->post(route('admin.certificate-requests.approve', $request))
            ->assertStatus(422);
    }

    public function test_approval_is_refused_once_the_resident_profile_is_archived(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());
        $request = CertificateRequest::firstOrFail();

        // The resident was active when they submitted, then archived before
        // the clerk got to the queue.
        $this->resident->update(['status' => 'Archived']);

        $this->actingAs($this->admin)
            ->post(route('admin.certificate-requests.approve', $request))
            ->assertSessionHasErrors('resident_id');

        $this->assertSame(0, CertificateIssuance::count());
        $this->assertSame('Pending', $request->fresh()->status);
    }

    public function test_a_second_approval_does_not_consume_another_control_number(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());
        $request = CertificateRequest::firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.certificate-requests.approve', $request));

        // Replaying the exact same request (double click, retried request) must
        // not mint a second, unreachable certificate.
        $this->actingAs($this->admin)
            ->post(route('admin.certificate-requests.approve', $request))
            ->assertStatus(422);

        $this->assertSame(1, CertificateIssuance::count());
    }

    public function test_admin_sees_reprint_link_for_approved_requests(): void
    {
        $this->actingAs($this->residentUser)->post('/my/requests', $this->validPayload());
        $request = CertificateRequest::firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.certificate-requests.approve', $request));

        $issuance = CertificateIssuance::firstOrFail();
        $this->actingAs($this->admin)->get('/admin/certificate-requests?status=Approved')
            ->assertOk()
            ->assertSee(route('certificates.print', $issuance))
            ->assertViewHas('requests', fn ($requests) => $requests->getCollection()->every(
                fn (CertificateRequest $request) => $request->relationLoaded('issuance'),
            ));
    }
}
