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

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_select_the_office_role_and_sign_in(): void
    {
        $staff = User::factory()->staff()->create();

        $this->assertTrue($staff->hasPermission('residents.view'));
        $this->assertTrue($staff->hasPermission('welfare.intake'));
        $this->assertFalse($staff->hasPermission('exports.view'));
        $this->assertFalse($staff->hasPermission('welfare.approve'));

        // Administrators and staff share the one office tab, so the login page
        // no longer offers a staff-only choice.
        $this->get('/login')
            ->assertOk()
            ->assertSee('name="user_type"', false)
            ->assertSee('Admin/Staff')
            ->assertDontSee('value="staff"', false);

        $response = $this->post('/login', [
            'email' => $staff->email,
            'password' => 'password',
            'user_type' => 'office',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_staff_login_still_requires_the_matching_role_group(): void
    {
        $staff = User::factory()->staff()->create();

        // The office tab admits staff; the resident tab must not.
        $response = $this->post('/login', [
            'email' => $staff->email,
            'password' => 'password',
            'user_type' => 'resident',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_staff_can_access_operational_modules(): void
    {
        $staff = User::factory()->staff()->create();

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
            $this->actingAs($staff)->get($url)->assertOk();
        }
    }

    public function test_staff_cannot_access_administrator_only_modules(): void
    {
        $staff = User::factory()->staff()->create();

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
            $this->actingAs($staff)->get($url)->assertRedirect(route('dashboard'));
        }

        $this->actingAs($staff)->get('/puroks/create')->assertRedirect(route('dashboard'));
    }

    public function test_staff_cannot_use_destructive_or_administrator_actions(): void
    {
        $staff = User::factory()->staff()->create();
        $resident = Resident::factory()->create();
        $household = Household::factory()->create();
        $blotter = Blotter::factory()->create();
        $welfare = Welfare::factory()->create();
        $issuance = CertificateIssuance::factory()->create();

        $this->actingAs($staff)
            ->post(route('residents.archive', $resident))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($staff)
            ->delete(route('households.destroy', $household))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($staff)
            ->delete(route('blotter.destroy', $blotter))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($staff)
            ->put(route('welfare.update', $welfare), [])
            ->assertRedirect(route('dashboard'));
        $this->actingAs($staff)
            ->post(route('certificates.void', $issuance))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('residents', ['id' => $resident->id, 'status' => 'Active']);
        $this->assertDatabaseHas('households', ['id' => $household->id]);
        $this->assertDatabaseHas('blotter', ['id' => $blotter->id]);
        $this->assertDatabaseHas('certificate_issuances', ['id' => $issuance->id, 'status' => 'Issued']);
    }

    public function test_staff_can_view_queues_but_cannot_decide_them(): void
    {
        $staff = User::factory()->staff()->create();
        $resident = Resident::factory()->create();
        $document = Document::factory()->create();
        $certificateRequest = CertificateRequest::create([
            'resident_id' => $resident->id,
            'document_id' => $document->id,
            'purpose' => 'Barangay requirement',
            'copies' => 1,
            'status' => 'Pending',
        ]);
        $correction = ResidentRecordChange::create([
            'resident_id' => $resident->id,
            'requested_by' => $staff->id,
            'changes' => ['occupation' => 'Teacher'],
            'status' => 'Pending',
        ]);

        $this->actingAs($staff)->get(route('admin.certificate-requests.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.resident-changes.index'))->assertOk();
        $this->actingAs($staff)
            ->post(route('admin.certificate-requests.approve', $certificateRequest))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($staff)
            ->post(route('admin.resident-changes.reject', $correction), ['review_note' => 'No.'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('Pending', $certificateRequest->fresh()->status);
        $this->assertSame('Pending', $correction->fresh()->status);
    }

    public function test_staff_cannot_change_a_resident_status_through_normal_update(): void
    {
        $staff = User::factory()->staff()->create();
        $resident = Resident::factory()->create(['status' => 'Active']);

        $this->actingAs($staff)->put(route('residents.update', $resident), [
            'first_name' => $resident->first_name,
            'last_name' => $resident->last_name,
            'sex' => $resident->sex,
            'civil_status' => $resident->civil_status,
            'status' => 'Archived',
        ])->assertRedirect(route('residents.index'));

        $this->assertSame('Active', $resident->fresh()->status);
    }

    public function test_staff_welfare_intake_cannot_approve_or_release_funds(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('welfare.store'), [
            'beneficiary_name' => 'Walk-in resident',
            'assistance_type' => 'Financial',
            'program_name' => 'Emergency assistance',
            'requested_amount' => 1000,
            'status' => 'Approved',
            'approved_amount' => 900,
            'approval_date' => now()->toDateString(),
            'request_date' => now()->toDateString(),
        ])->assertRedirect(route('welfare.index'));

        $welfare = Welfare::latest('id')->firstOrFail();

        $this->assertSame('Requested', $welfare->status);
        $this->assertEquals(0, (float) $welfare->approved_amount);
        $this->assertNull($welfare->approval_date);
        $this->assertNull($welfare->release_date);
    }

    public function test_staff_certificate_issuance_uses_the_catalog_fee(): void
    {
        $staff = User::factory()->staff()->create();
        $document = Document::factory()->create(['fee' => 75]);
        $resident = Resident::factory()->create();

        $this->actingAs($staff)->post(route('certificates.store'), [
            'document_id' => $document->id,
            'resident_id' => $resident->id,
            'purpose' => 'Employment requirement',
            'copies' => 1,
            'fee' => 0,
        ])->assertRedirect();

        $issuance = CertificateIssuance::latest('id')->firstOrFail();

        $this->assertSame(75.0, (float) $issuance->fee);
    }

    public function test_administrator_can_assign_the_staff_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $target), ['user_type' => 'staff'])
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertSame('staff', $target->fresh()->user_type);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'account.role_changed',
            'subject_id' => $target->id,
        ]);
    }

    public function test_office_role_cannot_be_assigned_to_a_linked_resident_account(): void
    {
        $admin = User::factory()->admin()->create();
        $residentAccount = User::factory()->resident()->create();
        Resident::factory()->create(['user_id' => $residentAccount->id]);

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $residentAccount), ['user_type' => 'staff'])
            ->assertSessionHasErrors('user_type');

        $this->assertSame('resident', $residentAccount->fresh()->user_type);
    }

    public function test_staff_cannot_open_the_resident_portal(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/my')->assertRedirect(route('dashboard'));
    }
}
