<?php

namespace Tests\Feature\Auth;

use App\Models\Blotter;
use App\Models\CertificateIssuance;
use App\Models\CertificateRequest;
use App\Models\Document;
use App\Models\Household;
use App\Models\Resident;
use App\Models\ResidentRecordChange;
use App\Models\User;
use App\Models\Welfare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The official tier reviews and decides. It reads every operational module
 * and settles the three approval queues, but it never enters clerical records
 * and never reaches a destructive or administrator-only action - the same
 * line staff may not cross, drawn from the other side.
 */
class OfficialAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_signs_in_through_the_office_tab(): void
    {
        $official = User::factory()->official()->create();

        $this->assertTrue($official->isOfficeUser());
        $this->assertTrue($official->hasPermission('residents.view'));
        $this->assertTrue($official->hasPermission('blotter.manage'));
        $this->assertTrue($official->hasPermission('welfare.approve'));
        $this->assertTrue($official->hasPermission('certificate-requests.decide'));
        $this->assertTrue($official->hasPermission('resident-changes.decide'));
        $this->assertTrue($official->hasPermission('reports.view'));

        $this->assertFalse($official->hasPermission('residents.manage'));
        $this->assertFalse($official->hasPermission('households.manage'));
        $this->assertFalse($official->hasPermission('welfare.intake'));
        $this->assertFalse($official->hasPermission('certificates.issue'));
        $this->assertFalse($official->hasPermission('exports.view'));
        $this->assertFalse($official->hasPermission('users.manage'));

        $this->post('/login', [
            'email' => $official->email,
            'password' => 'barangay-2026',
            'user_type' => 'office',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($official);
    }

    public function test_official_account_is_rejected_by_the_resident_tab(): void
    {
        $official = User::factory()->official()->create();

        $this->post('/login', [
            'email' => $official->email,
            'password' => 'barangay-2026',
            'user_type' => 'resident',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_official_can_read_every_operational_module(): void
    {
        $official = User::factory()->official()->create();

        foreach ([
            '/dashboard',
            '/residents',
            '/households',
            '/puroks',
            '/blotter',
            '/welfare',
            '/certificates',
            '/reports',
            '/analytics',
            '/admin/certificate-requests',
            '/admin/resident-changes',
        ] as $url) {
            $this->actingAs($official)->get($url)->assertOk();
        }
    }

    public function test_official_cannot_open_administrator_only_modules(): void
    {
        $official = User::factory()->official()->create();

        foreach ([
            '/admin/settings',
            '/admin/users',
            '/admin/approvals',
            '/admin/audit-logs',
            '/admin/mail-health',
            '/admin/certificate-types',
            '/archive',
            '/admin/exports/residents',
        ] as $url) {
            $this->actingAs($official)->get($url)->assertRedirect(route('dashboard'));
        }

        $this->actingAs($official)->get('/puroks/create')->assertRedirect(route('dashboard'));
    }

    public function test_the_sidebar_keeps_administration_out_of_official_view(): void
    {
        $official = User::factory()->official()->create();

        $this->actingAs($official)->get('/dashboard')
            ->assertOk()
            ->assertSee('/residents', false)
            ->assertDontSee('/admin/settings', false);
    }

    public function test_official_approves_a_pending_certificate_request(): void
    {
        $official = User::factory()->official()->create();
        $resident = Resident::factory()->create();
        $document = Document::factory()->create();
        $request = CertificateRequest::create([
            'resident_id' => $resident->id,
            'document_id' => $document->id,
            'purpose' => 'Barangay requirement',
            'copies' => 1,
            'status' => 'Pending',
        ]);

        $this->actingAs($official)
            ->post(route('admin.certificate-requests.approve', $request))
            ->assertRedirect();

        $this->assertSame('Approved', $request->fresh()->status);
        $this->assertDatabaseHas('certificate_issuances', [
            'resident_id' => $resident->id,
            'status' => 'Issued',
        ]);
    }

    public function test_official_rejects_a_pending_record_correction(): void
    {
        $official = User::factory()->official()->create();
        $resident = Resident::factory()->create();
        $change = ResidentRecordChange::create([
            'resident_id' => $resident->id,
            'requested_by' => $resident->user_id ?? User::factory()->resident()->create()->id,
            'changes' => ['occupation' => 'Teacher'],
            'status' => 'Pending',
        ]);

        $this->actingAs($official)
            ->post(route('admin.resident-changes.reject', $change), [
                'review_note' => 'The submitted name does not match the record.',
            ])
            ->assertRedirect(route('admin.resident-changes.index'));

        $this->assertSame('Rejected', $change->fresh()->status);
    }

    public function test_official_approves_an_assistance_request(): void
    {
        $official = User::factory()->official()->create();
        $welfare = Welfare::factory()->create(['status' => 'Requested']);

        $this->actingAs($official)->put(route('welfare.update', $welfare), [
            'beneficiary_name' => $welfare->beneficiary_name,
            'beneficiary_address' => $welfare->beneficiary_address,
            'beneficiary_phone' => $welfare->beneficiary_phone,
            'assistance_type' => 'Medical',
            'program_name' => 'Medical Assistance Program',
            'requested_amount' => 5000,
            'status' => 'Approved',
            'approved_amount' => 2500,
            'approval_date' => now()->toDateString(),
            'request_date' => now()->toDateString(),
        ])->assertRedirect(route('welfare.index'));

        $welfare->refresh();
        $this->assertSame('Approved', $welfare->status);
        $this->assertEquals(2500, (float) $welfare->approved_amount);
    }

    public function test_official_records_a_blotter_case(): void
    {
        $official = User::factory()->official()->create();

        $this->actingAs($official)->post('/blotter', $this->blotterPayload())
            ->assertRedirect('/blotter');

        $this->assertDatabaseCount('blotter', 1);
    }

    public function test_official_cannot_enter_clerical_records(): void
    {
        $official = User::factory()->official()->create();

        foreach ([
            'residents.store',
            'households.store',
            'certificates.store',
            'welfare.store',
        ] as $route) {
            $this->actingAs($official)->post(route($route), [])->assertRedirect(route('dashboard'));
        }

        $this->assertDatabaseCount('residents', 0);
        $this->assertDatabaseCount('households', 0);
        $this->assertDatabaseCount('welfare', 0);
    }

    public function test_official_cannot_use_destructive_actions(): void
    {
        $official = User::factory()->official()->create();
        $resident = Resident::factory()->create();
        $household = Household::factory()->create();
        $blotter = Blotter::factory()->create();
        $welfare = Welfare::factory()->create();
        $issuance = CertificateIssuance::factory()->create();

        $this->actingAs($official)
            ->post(route('residents.archive', $resident))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($official)
            ->delete(route('households.destroy', $household))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($official)
            ->delete(route('blotter.destroy', $blotter))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($official)
            ->delete(route('welfare.destroy', $welfare))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($official)
            ->post(route('certificates.void', $issuance))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('residents', ['id' => $resident->id, 'status' => 'Active']);
        $this->assertDatabaseHas('households', ['id' => $household->id]);
        $this->assertDatabaseHas('blotter', ['id' => $blotter->id]);
        $this->assertDatabaseHas('welfare', ['id' => $welfare->id]);
        $this->assertDatabaseHas('certificate_issuances', ['id' => $issuance->id, 'status' => 'Issued']);
    }

    public function test_administrator_can_assign_the_official_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $target), ['user_type' => 'official'])
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertSame('official', $target->fresh()->user_type);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'account.role_changed',
            'subject_id' => $target->id,
        ]);
    }

    public function test_official_cannot_open_the_resident_portal(): void
    {
        $official = User::factory()->official()->create();

        foreach (['/my', '/my/requests', '/my/blotter', '/my/welfare', '/my/officials'] as $url) {
            $this->actingAs($official)->get($url)->assertRedirect(route('dashboard'));
        }
    }

    private function blotterPayload(array $overrides = []): array
    {
        return array_merge([
            'complainant_name' => 'Maria Santos',
            'complainant_address' => 'Purok 1, Rizal St.',
            'complainant_phone' => '09171234567',
            'accused_name' => 'Juan Dela Cruz',
            'accused_address' => 'Purok 2, Mabini Ave.',
            'accused_phone' => '09181234567',
            'complaint_type' => 'Noise Complaint',
            'complaint_date' => now()->toDateString(),
            'complaint_time' => '21:30',
            'alleged_offense' => 'Loud videoke singing past curfew disturbing neighbors.',
            'status' => 'Open',
            'arrest_made' => 'No',
        ], $overrides);
    }
}
