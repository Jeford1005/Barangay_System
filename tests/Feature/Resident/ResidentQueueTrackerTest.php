<?php

namespace Tests\Feature\Resident;

use App\Models\Blotter;
use App\Models\CertificateRequest;
use App\Models\Document;
use App\Models\Resident;
use App\Models\User;
use App\Models\Welfare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Resident request tracker: queue position + honest ETA on the portal's
 * pending certificate, blotter, and welfare cards.
 *
 * Positions count still-pending same-type rows across residents (that is
 * what makes a queue a queue) but only ever as numbers — no other
 * resident's content may appear on the page.
 */
class ResidentQueueTrackerTest extends TestCase
{
    use RefreshDatabase;

    private function approvedResident(): array
    {
        $user = User::factory()->create(['user_type' => 'resident', 'status' => 'approved']);
        $resident = Resident::factory()->create(['user_id' => $user->id]);

        return [$user, $resident];
    }

    private function certificateDocument(string $code = 'CLR'): Document
    {
        return Document::where('code', $code)->firstOrFail();
    }

    public function test_certificate_position_counts_pending_ahead_across_residents(): void
    {
        $document = $this->certificateDocument();
        $made = [];

        foreach (range(0, 2) as $i) {
            [$user, $resident] = $this->approvedResident();
            $request = CertificateRequest::create([
                'resident_id' => $resident->id,
                'document_id' => $document->id,
                'purpose' => "Queue purpose {$i}",
                'copies' => 1,
                'status' => 'Pending',
            ]);
            // Stagger creation so the queue order is unambiguous.
            $stamped = now()->subHours(3 - $i);
            $request->created_at = $stamped;
            $request->updated_at = $stamped;
            $request->save();
            $made[] = [$user, $resident, $request];
        }

        $this->actingAs($made[1][0])
            ->get('/my/requests')
            ->assertOk()
            ->assertSee('Position 2 of 3')
            // Thin data (no decided rows yet): position only, no ETA guess.
            ->assertDontSee('usually ready')
            // Only numbers cross residents: nobody else's rows are listed.
            ->assertSee('Queue purpose 1')
            ->assertDontSee('Queue purpose 0')
            ->assertDontSee('Queue purpose 2')
            ->assertDontSee($made[0][1]->full_name)
            ->assertDontSee($made[2][1]->full_name);
    }

    public function test_certificate_eta_appears_with_enough_recent_completions(): void
    {
        [$user, $resident] = $this->approvedResident();
        $document = $this->certificateDocument();

        CertificateRequest::create([
            'resident_id' => $resident->id,
            'document_id' => $document->id,
            'purpose' => 'My clearance purpose',
            'copies' => 1,
            'status' => 'Pending',
        ]);

        // 30 decided same-type rows in the window: 30 / 30 = 1/day, so the
        // lone pending request is ~1 day out.
        foreach (range(1, 30) as $i) {
            $other = Resident::factory()->create();
            CertificateRequest::create([
                'resident_id' => $other->id,
                'document_id' => $document->id,
                'purpose' => "Decided purpose {$i}",
                'copies' => 1,
                'status' => 'Approved',
            ]);
        }

        $this->actingAs($user)
            ->get('/my/requests')
            ->assertOk()
            ->assertSee('Position 1 of 1')
            ->assertSee('usually ready in ~1 day');
    }

    public function test_certificate_eta_hidden_with_fewer_than_five_completions(): void
    {
        [$user, $resident] = $this->approvedResident();
        $document = $this->certificateDocument();

        CertificateRequest::create([
            'resident_id' => $resident->id,
            'document_id' => $document->id,
            'purpose' => 'My clearance purpose',
            'copies' => 1,
            'status' => 'Pending',
        ]);

        foreach (range(1, 4) as $i) {
            $other = Resident::factory()->create();
            CertificateRequest::create([
                'resident_id' => $other->id,
                'document_id' => $document->id,
                'purpose' => "Decided purpose {$i}",
                'copies' => 1,
                'status' => 'Approved',
            ]);
        }

        $this->actingAs($user)
            ->get('/my/requests')
            ->assertOk()
            ->assertSee('Position 1 of 1')
            ->assertDontSee('usually ready');
    }

    public function test_certificate_decided_requests_show_outcome_not_position(): void
    {
        [$user, $resident] = $this->approvedResident();
        $document = $this->certificateDocument();

        CertificateRequest::create([
            'resident_id' => $resident->id,
            'document_id' => $document->id,
            'purpose' => 'Still waiting purpose',
            'copies' => 1,
            'status' => 'Pending',
        ]);
        // Decided same-type rows measure pace but never join the queue.
        CertificateRequest::create([
            'resident_id' => $resident->id,
            'document_id' => $document->id,
            'purpose' => 'Turned down purpose',
            'copies' => 1,
            'status' => 'Rejected',
            'rejection_reason' => 'Needs a hearing first',
        ]);

        $html = $this->actingAs($user)->get('/my/requests')->assertOk()->getContent();

        $this->assertStringContainsString('Position 1 of 1', $html);
        $this->assertStringContainsString('Needs a hearing first', $html);
        $this->assertSame(1, substr_count($html, 'Position '));
    }

    public function test_certificate_other_type_pending_does_not_move_my_position(): void
    {
        [$user, $resident] = $this->approvedResident();

        CertificateRequest::create([
            'resident_id' => $resident->id,
            'document_id' => $this->certificateDocument('CLR')->id,
            'purpose' => 'My clearance purpose',
            'copies' => 1,
            'status' => 'Pending',
        ]);

        [$otherUser, $otherResident] = $this->approvedResident();
        CertificateRequest::create([
            'resident_id' => $otherResident->id,
            'document_id' => $this->certificateDocument('IND')->id,
            'purpose' => 'Other resident other type purpose',
            'copies' => 1,
            'status' => 'Pending',
        ]);

        $this->actingAs($user)
            ->get('/my/requests')
            ->assertOk()
            ->assertSee('Position 1 of 1')
            ->assertDontSee('Other resident other type purpose')
            ->assertDontSee($otherResident->full_name);

        // Sanity: the other resident still sees their own queue line.
        $this->actingAs($otherUser)
            ->get('/my/requests')
            ->assertOk()
            ->assertSee('Position 1 of 1')
            ->assertDontSee('My clearance purpose');
    }

    public function test_blotter_position_counts_same_type_open_cases(): void
    {
        $made = [];

        foreach (range(0, 2) as $i) {
            [$user, $resident] = $this->approvedResident();
            $case = Blotter::factory()->create([
                'complainant_id' => $resident->id,
                'complainant_name' => $resident->full_name,
                'reported_by_resident' => true,
                'complaint_type' => 'Queue Noise Probe',
                'alleged_offense' => "Loud videoke night {$i}.",
                'status' => 'Open',
            ]);
            $stamped = now()->subHours(3 - $i);
            $case->created_at = $stamped;
            $case->updated_at = $stamped;
            $case->save();
            $made[] = [$user, $resident, $case];
        }

        // A different-type case from another resident: neither counted nor shown.
        [$extraUser, $extraResident] = $this->approvedResident();
        Blotter::factory()->create([
            'complainant_id' => $extraResident->id,
            'complainant_name' => $extraResident->full_name,
            'reported_by_resident' => true,
            'complaint_type' => 'Unrelated Quiet Probe',
            'status' => 'Open',
        ]);

        $this->actingAs($made[1][0])
            ->get('/my/blotter')
            ->assertOk()
            ->assertSee('Position 2 of 3')
            ->assertDontSee('usually ready')
            ->assertDontSee('Unrelated Quiet Probe')
            ->assertDontSee($made[0][2]->case_number)
            ->assertDontSee($made[2][2]->case_number);
    }

    public function test_blotter_resolved_cases_show_no_position(): void
    {
        [$user, $resident] = $this->approvedResident();

        Blotter::factory()->create([
            'complainant_id' => $resident->id,
            'complainant_name' => $resident->full_name,
            'reported_by_resident' => true,
            'complaint_type' => 'Queue Noise Probe',
            'status' => 'Open',
        ]);
        Blotter::factory()->create([
            'complainant_id' => $resident->id,
            'complainant_name' => $resident->full_name,
            'reported_by_resident' => true,
            'complaint_type' => 'Queue Noise Probe',
            'status' => 'Resolved',
            'disposition' => 'Settled amicably at the hall',
        ]);

        $html = $this->actingAs($user)->get('/my/blotter')->assertOk()->getContent();

        $this->assertStringContainsString('Position 1 of 1', $html);
        $this->assertStringContainsString('Settled amicably at the hall', $html);
        $this->assertSame(1, substr_count($html, 'Position '));
    }

    public function test_welfare_eta_caps_beyond_thirty_days(): void
    {
        [$user, $resident] = $this->approvedResident();
        $today = now()->toDateString();

        // 35 waiting same-type rows: with the 1/day floor the last one is
        // 35 days out, which must cap instead of printing "~35 days".
        foreach (range(1, 35) as $i) {
            Welfare::factory()->create([
                'beneficiary_id' => $resident->id,
                'beneficiary_name' => $resident->full_name,
                'assistance_type' => 'Medical',
                'program_name' => 'Queue Cap Program',
                'status' => 'Requested',
                'request_date' => $today,
            ]);
        }
        foreach (range(1, 5) as $i) {
            Welfare::factory()->approved()->create([
                'beneficiary_id' => Resident::factory()->create()->id,
                'assistance_type' => 'Medical',
                'program_name' => "Decided program {$i}",
                'request_date' => $today,
            ]);
        }

        $this->actingAs($user)
            ->get('/my/welfare')
            ->assertOk()
            ->assertSee('Position 35 of 35')
            ->assertSee('more than 30 days')
            ->assertDontSee('~35 days');
    }

    public function test_welfare_thin_data_shows_position_only_and_no_leakage(): void
    {
        [$user, $resident] = $this->approvedResident();

        Welfare::factory()->create([
            'beneficiary_id' => $resident->id,
            'beneficiary_name' => $resident->full_name,
            'assistance_type' => 'Medical',
            'program_name' => 'My medical program probe',
            'status' => 'Requested',
            'request_date' => now()->toDateString(),
        ]);
        Welfare::factory()->approved()->create([
            'beneficiary_id' => $resident->id,
            'beneficiary_name' => $resident->full_name,
            'assistance_type' => 'Medical',
            'program_name' => 'My approved program probe',
            'request_date' => now()->toDateString(),
        ]);

        // Another resident, another type: neither counted nor shown.
        [$otherUser, $otherResident] = $this->approvedResident();
        Welfare::factory()->create([
            'beneficiary_id' => $otherResident->id,
            'beneficiary_name' => $otherResident->full_name,
            'assistance_type' => 'Food',
            'program_name' => 'Other food program probe',
            'status' => 'Requested',
            'request_date' => now()->toDateString(),
        ]);

        $html = $this->actingAs($user)->get('/my/welfare')->assertOk()->getContent();

        $this->assertStringContainsString('Position 1 of 1', $html);
        $this->assertStringContainsString('My medical program probe', $html);
        $this->assertStringNotContainsString('usually ready', $html);
        $this->assertSame(1, substr_count($html, 'Position '));
        $this->assertStringNotContainsString('Other food program probe', $html);
        $this->assertStringNotContainsString($otherResident->full_name, $html);
    }
}
