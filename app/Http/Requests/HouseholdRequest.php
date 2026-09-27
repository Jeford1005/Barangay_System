<?php

namespace App\Http\Requests;

use App\Models\Household;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared behaviour for the household create/update forms.
 *
 * The head of family field carries the only non-trivial rule: the resident
 * must exist, be active, and either be unassigned or already belong to the
 * very household being edited.
 */
abstract class HouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('households.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function commonRules(): array
    {
        return [
            'address' => ['required', 'string', 'max:255'],
            'purok_id' => ['nullable', 'integer', 'exists:puroks,id'],
            'head_resident_id' => ['nullable', 'integer', 'exists:residents,id'],
            'house_type' => ['required', 'string', Rule::in(Household::HOUSE_TYPES)],
            'ownership' => ['required', 'string', Rule::in(Household::OWNERSHIPS)],
            'status' => ['required', 'string', Rule::in(Household::STATUSES)],
        ];
    }

    /**
     * Friendly messages for every enum / uniqueness rule in the form.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'household_number.unique' => 'That household number is already in use.',
            'household_number.max' => 'Household numbers may not be longer than 20 characters.',
            'purok_id.exists' => 'The selected purok does not exist.',
            'head_resident_id.exists' => 'The selected head of household does not exist.',
            'house_type.in' => 'Choose a valid house type.',
            'ownership.in' => 'Choose a valid ownership type.',
            'status.in' => 'Choose a valid household status.',
        ];
    }

    /** Append the head-of-family business rules to the validator. */
    protected function checkHeadOfHousehold(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $headId = $this->input('head_resident_id');

            if ($headId === null || $headId === '' || $validator->errors()->has('head_resident_id')) {
                return;
            }

            $resident = Resident::find($headId);

            if ($resident === null) {
                $validator->errors()->add('head_resident_id', 'That head of household record no longer exists.');

                return;
            }

            $household = $this->household();

            // An unchanged head never has to re-qualify (they may have been
            // archived since the household was filed).
            if ($household !== null && (int) $resident->id === (int) $household->head_resident_id) {
                return;
            }

            if ($resident->status !== Resident::STATUS_ACTIVE) {
                $validator->errors()->add('head_resident_id', 'The head of household must be an active resident.');

                return;
            }

            $householdId = $household?->id;

            if ($resident->household_id !== null && (int) $resident->household_id !== (int) $householdId) {
                $validator->errors()->add('head_resident_id', 'That resident already belongs to another household.');
            }
        });
    }

    /** The household being edited — null while creating. */
    protected function household(): ?Household
    {
        $household = $this->route('household');

        return $household instanceof Household ? $household : null;
    }
}
