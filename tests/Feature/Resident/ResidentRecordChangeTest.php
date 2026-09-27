<?php

namespace Tests\Feature\Resident;

use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentRecordChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentRecordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_submit_and_cancel_a_correction_request(): void
    {
        $user = User::factory()->resident()->create();
        $resident = Resident::factory()->create(['user_id' => $user->id, 'occupation' => 'Student']);

        $this->actingAs($user)
            ->get('/my/changes')
            ->assertOk()
            ->assertSee('Request a profile correction');

        $this->actingAs($user)
            ->post('/my/changes', [
                'occupation' => 'Teacher',
                'notes' => 'Updated employment record.',
            ])
            ->assertRedirect('/my/changes')
            ->assertSessionHas('success');

        $change = ResidentRecordChange::firstOrFail();
        $this->assertSame('Pending', $change->status);
        $this->assertSame('Student', $resident->fresh()->occupation);

        $this->actingAs($user)
            ->post(route('resident.changes.cancel', $change))
            ->assertRedirect();

        $this->assertSame('Cancelled', $change->fresh()->status);
    }

    public function test_admin_approval_applies_only_allowed_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->resident()->create();
        $resident = Resident::factory()->create(['user_id' => $user->id, 'occupation' => 'Student']);
        $purok = Purok::factory()->create();
        $change = ResidentRecordChange::create([
            'resident_id' => $resident->id,
            'requested_by' => $user->id,
            'changes' => [
                'occupation' => 'Teacher',
                'purok_id' => $purok->id,
                'status' => 'Archived',
            ],
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.resident-changes.approve', $change), [
                'review_note' => 'Supporting documents verified.',
            ])
            ->assertRedirect(route('admin.resident-changes.index'))
            ->assertSessionHas('success');

        $resident->refresh();
        $this->assertSame('Teacher', $resident->occupation);
        $this->assertSame($purok->id, $resident->purok_id);
        $this->assertNotSame('Archived', $resident->status);
        $this->assertSame('Approved', $change->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'resident.change_approved']);
    }

    public function test_resident_cannot_review_or_cancel_another_request(): void
    {
        $firstUser = User::factory()->resident()->create();
        $secondUser = User::factory()->resident()->create();
        $first = Resident::factory()->create(['user_id' => $firstUser->id]);
        $second = Resident::factory()->create(['user_id' => $secondUser->id]);
        $change = ResidentRecordChange::create([
            'resident_id' => $first->id,
            'requested_by' => $firstUser->id,
            'changes' => ['occupation' => 'Teacher'],
            'status' => 'Pending',
        ]);

        $this->actingAs($secondUser)
            ->post(route('resident.changes.cancel', $change))
            ->assertForbidden();

        $this->assertSame('Pending', $change->fresh()->status);
    }

    public function test_admin_rejection_requires_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->resident()->create();
        $resident = Resident::factory()->create(['user_id' => $user->id]);
        $change = ResidentRecordChange::create([
            'resident_id' => $resident->id,
            'requested_by' => $user->id,
            'changes' => ['occupation' => 'Teacher'],
            'status' => 'Pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.resident-changes.reject', $change))
            ->assertSessionHasErrors('review_note');

        $this->actingAs($admin)
            ->post(route('admin.resident-changes.reject', $change), ['review_note' => 'No supporting record.'])
            ->assertRedirect();

        $this->assertSame('Rejected', $change->fresh()->status);
    }
}
