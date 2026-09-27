<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Staff editing a household — unique number with the record itself ignored. */
class UpdateHouseholdRequest extends HouseholdRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'household_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('households', 'household_number')->ignore($this->route('household')),
            ],
        ] + $this->commonRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->checkHeadOfHousehold($validator);
    }
}
