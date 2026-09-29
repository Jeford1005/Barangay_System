<?php

namespace Tests\Feature\Resident;

use App\Models\Blotter;
use App\Models\Resident;
use App\Models\User;
use App\Models\Welfare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['user_type' => 'admin']);
    }

    private function approvedResident(): array
    {
        $user = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        $resident = Resident::factory()->create(['user_id' => $user->id]);

        return [$user, $resident];
    }

    private function validReport(array $overrides = []): array
    {
        return array_merge([
            'complaint_type' => 'Noise Complaint',
            'complaint_date' => now()->subDay()->toDateString(),
            'accused_name' => 'Pedro Reyes',
            'alleged_offense' => 'Loud videoke singing past curfew for three nights.',
        ], $overrides);
    }

    private function validAssistance(array $overrides = []): array
    {
        return array_merge([
            'assistance_type' => 'Medical',
            'program_name' => 'Medical Assistance Program',
            'requested_amount' => '1500.00',
            'remarks' => 'Prescription medicines for my mother.',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Incident reports
    // ------------------------------------------------------------------

    public function test_resident_sees_the_incident_report_form(): void
    {
        [$user] = $this->approvedResident();

        $this->actingAs($user)
            ->get('/my/blotter')
            ->assertOk()
            ->assertSee('Report Incident')
            ->assertSee('New report')
            ->assertSee('Reports');
    }

    public function test_resident_can_file_an_incident_report(): void
    {
        [$user, $resident] = $this->approvedResident();

        $this->actingAs($user)
            ->post('/my/blotter', $this->validReport())
            ->assertRedirect()
            ->assertSessionHas('success');

        $case = Blotter::sole();

        $this->assertSame($resident->id, $case->complainant_id);
        $this->assertSame($resident->full_name, $case->complainant_name);
        $this->assertSame('Noise Complaint', $case->complaint_type);
        $this->assertSame('Pedro Reyes', $case->accused_name);
        $this->assertSame('Open', $case->status);
        $this->assertSame('No', $case->arrest_made);
        $this->assertSame($user->id, $case->created_by);
        $this->assertTrue($case->reported_by_resident);
        $this->assertMatchesRegularExpression('/^BLTR-\d{4}-\d{4}$/', $case->case_number);
    }

    public function test_a_filed_report_reaches_the_office_blotter_queue(): void
    {
        [$user] = $this->approvedResident();

        $this->actingAs($user)->post('/my/blotter', $this->validReport());

        $this->actingAs($this->admin)
            ->get('/blotter')
            ->assertOk()
            ->assertSee('Noise Complaint')
            ->assertSee('Resident-reported');
    }

    public function test_report_validation_requires_the_core_fields(): void
    {
        [$user] = $this->approvedResident();

        $this->actingAs($user)
            ->post('/my/blotter', [])
            ->assertSessionHasErrors(['complaint_type', 'complaint_date', 'accused_name', 'alleged_offense']);

        $this->assertSame(0, Blotter::count());
    }

    public function test_incident_date_cannot_be_in_the_future(): void
    {
        [$user] = $this->approvedResident();

        $this->actingAs($user)
            ->post('/my/blotter', $this->validReport(['complaint_date' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('complaint_date');

        $this->assertSame(0, Blotter::count());
    }

    public function test_a_resident_sees_only_the_reports_they_filed(): void
    {
        [$user, $resident] = $this->approvedResident();

        Blotter::factory()->create([
            'complainant_id' => $resident->id,
            'reported_by_resident' => true,
            'complaint_type' => 'Filed by me',
        ]);
        // Same resident, but taken at the desk by staff — not theirs to follow here.
        Blotter::factory()->create([
            'complainant_id' => $resident->id,
            'reported_by_resident' => false,
            'complaint_type' => 'Taken at the desk',
        ]);
        Blotter::factory()->create(['complaint_type' => 'Someone elses report']);

        $this->actingAs($user)
            ->get('/my/blotter')
            ->assertOk()
            ->assertSee('Filed by me')
            ->assertDontSee('Taken at the desk')
            ->assertDontSee('Someone elses report');
    }

    public function test_archived_resident_cannot_file_a_report(): void
    {
        $user = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        Resident::factory()->create(['user_id' => $user->id])->update(['status' => 'Archived']);

        $this->actingAs($user)->get('/my/blotter')->assertForbidden();
        $this->actingAs($user)->post('/my/blotter', $this->validReport())->assertForbidden();

        $this->assertSame(0, Blotter::count());
    }

    public function test_guests_are_sent_to_login_from_the_report_page(): void
    {
        $this->get('/my/blotter')->assertRedirect(route('login'));
        $this->post('/my/blotter', $this->validReport())->assertRedirect(route('login'));
    }

    public function test_office_users_are_redirected_away_from_the_resident_report_page(): void
    {
        $this->actingAs($this->admin)->get('/my/blotter')->assertRedirect(route('dashboard'));
    }

    // ------------------------------------------------------------------
    // Assistance requests
    // ------------------------------------------------------------------

    public function test_resident_sees_the_assistance_request_form(): void
    {
        [$user] = $this->approvedResident();

        $this->actingAs($user)
            ->get('/my/welfare')
            ->assertOk()
            ->assertSee('Request assistance')
            ->assertSee('New assistance request')
            ->assertSee('Assistance requests');
    }

    public function test_resident_can_submit_an_assistance_request(): void
    {
        [$user, $resident] = $this->approvedResident();

        $this->actingAs($user)
            ->post('/my/welfare', $this->validAssistance())
            ->assertRedirect()
            ->assertSessionHas('success');

        $entry = Welfare::sole();

        $this->assertSame($resident->id, $entry->beneficiary_id);
        $this->assertSame($resident->full_name, $entry->beneficiary_name);
        $this->assertSame('Medical', $entry->assistance_type);
        $this->assertSame('Medical Assistance Program', $entry->program_name);
        $this->assertSame('Requested', $entry->status);
        $this->assertSame(now()->toDateString(), $entry->request_date->toDateString());
        $this->assertNull($entry->approval_date);
        $this->assertSame('0.00', $entry->approved_amount);
    }

    public function test_a_submitted_request_reaches_the_office_welfare_queue(): void
    {
        [$user] = $this->approvedResident();

        $this->actingAs($user)->post('/my/welfare', $this->validAssistance());

        $this->actingAs($this->admin)
            ->get('/welfare')
            ->assertOk()
            ->assertSee('Medical Assistance Program')
            ->assertSee('Requested');
    }

    public function test_assistance_validation_requires_the_core_fields(): void
    {
        [$user] = $this->approvedResident();

        $this->actingAs($user)
            ->post('/my/welfare', [])
            ->assertSessionHasErrors(['assistance_type', 'program_name', 'requested_amount']);

        $this->assertSame(0, Welfare::count());
    }

    public function test_assistance_amount_rejects_letters(): void
    {
        [$user] = $this->approvedResident();

        $this->actingAs($user)
            ->post('/my/welfare', $this->validAssistance(['requested_amount' => 'lots']))
            ->assertSessionHasErrors('requested_amount');

        $this->assertSame(0, Welfare::count());
    }

    public function test_a_resident_sees_only_their_own_assistance_requests(): void
    {
        [$user, $resident] = $this->approvedResident();

        Welfare::factory()->create([
            'beneficiary_id' => $resident->id,
            'program_name' => 'My own request',
        ]);
        Welfare::factory()->create(['program_name' => 'Someone elses request']);

        $this->actingAs($user)
            ->get('/my/welfare')
            ->assertOk()
            ->assertSee('My own request')
            ->assertDontSee('Someone elses request');
    }

    public function test_archived_resident_cannot_request_assistance(): void
    {
        $user = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        Resident::factory()->create(['user_id' => $user->id])->update(['status' => 'Archived']);

        $this->actingAs($user)->get('/my/welfare')->assertForbidden();
        $this->actingAs($user)->post('/my/welfare', $this->validAssistance())->assertForbidden();

        $this->assertSame(0, Welfare::count());
    }

    public function test_guests_are_sent_to_login_from_the_assistance_page(): void
    {
        $this->get('/my/welfare')->assertRedirect(route('login'));
        $this->post('/my/welfare', $this->validAssistance())->assertRedirect(route('login'));
    }
}
