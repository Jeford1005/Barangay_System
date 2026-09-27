<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Resident;
use Illuminate\Validation\ValidationException;

class HouseholdResidentSync
{
    /**
     * Keep a household's head foreign key and the resident head flag aligned.
     */
    public function syncHouseholdHead(Household $household, ?int $headId): void
    {
        $headId = $headId ?: null;

        if ($headId) {
            $head = Resident::query()->lockForUpdate()->find($headId);

            if (! $head) {
                throw ValidationException::withMessages([
                    'head_of_household_id' => 'The selected head of household is no longer available.',
                ]);
            }

            if ($head->status !== 'Active') {
                throw ValidationException::withMessages([
                    'head_of_household_id' => 'Archived residents cannot be assigned as household head.',
                ]);
            }

            if ($head->household_id && $head->household_id !== $household->id) {
                throw ValidationException::withMessages([
                    'head_of_household_id' => 'That resident is already assigned to another household.',
                ]);
            }
        }

        $now = now();

        Resident::query()
            ->where('household_id', $household->id)
            ->where('is_household_head', true)
            ->when($headId, fn ($query) => $query->where('id', '!=', $headId))
            ->update(['is_household_head' => false, 'updated_at' => $now]);

        if ($headId) {
            Resident::query()
                ->whereKey($headId)
                ->update([
                    'household_id' => $household->id,
                    'is_household_head' => true,
                    'updated_at' => $now,
                ]);
        }

        $household->head_of_household_id = $headId;
        $household->save();
    }

    /**
     * Synchronize a resident after its household or head flag changes.
     */
    public function syncResidentAfterSave(
        Resident $resident,
        ?int $previousHouseholdId = null,
    ): void {
        $newHouseholdId = $resident->household_id;

        if ($previousHouseholdId && $previousHouseholdId !== $newHouseholdId) {
            $previousHousehold = Household::query()
                ->lockForUpdate()
                ->find($previousHouseholdId);

            if ($previousHousehold?->head_of_household_id === $resident->id) {
                $previousHousehold->head_of_household_id = null;
                $previousHousehold->save();

                Resident::query()
                    ->whereKey($resident->id)
                    ->update(['is_household_head' => false, 'updated_at' => now()]);

                $resident->is_household_head = false;
            }
        }

        if ($resident->is_household_head) {
            if (! $newHouseholdId) {
                throw ValidationException::withMessages([
                    'is_household_head' => 'Assign the resident to a household before marking them as household head.',
                ]);
            }

            $household = Household::query()
                ->lockForUpdate()
                ->find($newHouseholdId);

            if (! $household) {
                throw ValidationException::withMessages([
                    'household_id' => 'The selected household is no longer available.',
                ]);
            }

            $this->syncHouseholdHead($household, $resident->id);

            return;
        }

        if ($newHouseholdId) {
            $household = Household::query()
                ->lockForUpdate()
                ->find($newHouseholdId);

            if ($household?->head_of_household_id === $resident->id) {
                $household->head_of_household_id = null;
                $household->save();
            }
        }
    }
}
