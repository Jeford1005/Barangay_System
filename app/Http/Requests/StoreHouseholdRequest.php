<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

/** Staff creating a household — the number is generated for the user. */
class StoreHouseholdRequest extends HouseholdRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'household_number' => ['required', 'string', 'max:20', 'unique:households,household_number'],
        ] + $this->commonRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->checkHeadOfHousehold($validator);
    }
}
