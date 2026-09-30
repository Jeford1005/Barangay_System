<?php

namespace Tests\Feature\Household;

use App\Models\Household;
use App\Models\Resident;
use App\Services\HouseholdResidentSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Direct coverage for the HouseholdResidentSync service. The HTTP suites pin
 * the common paths (assign, reassign, cross-household rejection) through the
 * controllers; these pin the service guards the controllers rely on:
 * archived heads, clearing a head, vacating a previous household, and the
 * head-without-household invariant.
 */
class HouseholdSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private HouseholdResidentSync $sync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sync = new HouseholdResidentSync;
    }

    public function test_clearing_the_head_unflags_the_previous_resident(): void
    {
        $household = Household::factory()->create();
        $head = Resident::factory()->create([
            'household_id' => $household->id,
            'is_household_head' => true,
        ]);
        $household->update(['head_of_household_id' => $head->id]);

        $this->sync->syncHouseholdHead($household, null);

        $this->assertNull($household->fresh()->head_of_household_id);
        $this->assertFalse($head->fresh()->is_household_head);
    }

    public function test_archived_residents_cannot_be_assigned_as_head(): void
    {
        $household = Household::factory()->create();
        $archived = Resident::factory()->create(['status' => 'Archived']);

        $this->expectException(ValidationException::class);

        $this->sync->syncHouseholdHead($household, $archived->id);
    }

    public function test_a_head_already_in_another_household_is_rejected(): void
    {
        $first = Household::factory()->create();
        $second = Household::factory()->create();
        $resident = Resident::factory()->create(['household_id' => $first->id]);

        $this->expectException(ValidationException::class);

        $this->sync->syncHouseholdHead($second, $resident->id);
    }

    public function test_a_resident_leaving_a_household_vacates_the_old_head_slot(): void
    {
        $previous = Household::factory()->create();
        $next = Household::factory()->create();
        $head = Resident::factory()->create([
            'household_id' => $previous->id,
            'is_household_head' => true,
        ]);
        $previous->update(['head_of_household_id' => $head->id]);

        // Mirror the controller flow: the resident already points at the new
        // household while still carrying the head flag.
        $head->household_id = $next->id;
        $this->sync->syncResidentAfterSave($head, $previous->id);

        $this->assertNull($previous->fresh()->head_of_household_id);
        $this->assertFalse($head->fresh()->is_household_head);
    }

    public function test_marking_a_head_requires_a_household(): void
    {
        $resident = Resident::factory()->create([
            'household_id' => null,
            'is_household_head' => true,
        ]);

        $this->expectException(ValidationException::class);

        $this->sync->syncResidentAfterSave($resident);
    }
}
