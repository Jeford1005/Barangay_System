<?php

namespace App\Http\Requests;

use App\Models\Resident;
use App\Models\Welfare;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared behaviour for the welfare intake / review forms: the resident must
 * be an active record, and every field is a fixed choice from the model's
 * constants.
 */
abstract class WelfareRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function commonRules(): array
    {
        return [
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'assistance_type' => ['required', 'string', Rule::in(Welfare::ASSISTANCE_TYPES)],
            'requested_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'request_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resident_id.exists' => 'The selected resident does not exist.',
            'assistance_type.in' => 'Choose a valid assistance type.',
            'requested_amount.min' => 'The requested amount cannot be negative.',
            'requested_amount.max' => 'The requested amount cannot be greater than ₱99,999,999.99.',
            'request_date.before_or_equal' => 'The request date cannot be in the future.',
        ];
    }

    /** The selected resident must exist and still be active. */
    protected function checkResident(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $residentId = $this->input('resident_id');

            if ($residentId === null || $residentId === '' || $validator->errors()->has('resident_id')) {
                return;
            }

            // A record keeps the resident it was filed for, even if that
            // resident has since been archived (otherwise the request could
            // never be reviewed again).
            $current = $this->route('welfare');

            if ($current instanceof Welfare && (int) $residentId === (int) $current->resident_id) {
                return;
            }

            $resident = Resident::find($residentId);

            if ($resident === null) {
                $validator->errors()->add('resident_id', 'That resident record no longer exists.');

                return;
            }

            if ($resident->status !== Resident::STATUS_ACTIVE) {
                $validator->errors()->add('resident_id', 'The selected resident must be an active resident.');
            }
        });
    }
}
