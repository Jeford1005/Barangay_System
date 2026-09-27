<?php

namespace App\Http\Requests;

use App\Models\Welfare;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Administrator reviewing an assistance request: adds the status workflow
 * (approve/release need a granted amount, denial needs a reason, and nothing
 * may be granted beyond what was requested).
 */
class UpdateWelfareRequest extends WelfareRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->commonRules() + [
            'status' => ['required', 'string', Rule::in(Welfare::STATUSES)],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'status.in' => 'Choose a valid assistance status.',
            'amount.min' => 'The granted amount cannot be negative.',
            'amount.max' => 'The granted amount cannot be greater than ₱99,999,999.99.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->checkResident($validator);
        $this->checkWorkflow($validator);
    }

    /** Status / granted-amount invariants for the review workflow. */
    private function checkWorkflow(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('amount') || $validator->errors()->has('status')) {
                return;
            }

            $status = (string) $this->input('status');
            $amount = $this->amountValue();
            $notes = trim((string) $this->input('notes'));

            if (in_array($status, ['Approved', 'Released'], true)) {
                if ($amount === null) {
                    $validator->errors()->add(
                        'amount',
                        $status === 'Approved'
                            ? 'A granted amount is required to approve this request.'
                            : 'A granted amount is required before releasing this assistance.'
                    );
                } elseif ($amount <= 0) {
                    $validator->errors()->add('amount', 'The granted amount must be greater than ₱0.00.');
                }
            } elseif ($status === 'Denied') {
                if ($notes === '') {
                    $validator->errors()->add('notes', 'Please state the reason for denial in the notes.');
                }
            } elseif ($amount !== null && $amount > 0) {
                // Requested / Under Review — nothing may be granted yet.
                $validator->errors()->add('amount', 'The granted amount must be empty while the request is still '.strtolower($status).'.');
            }

            if ($amount === null || $amount <= 0 || $validator->errors()->has('requested_amount')) {
                return;
            }

            $requested = $this->input('requested_amount');

            if (is_numeric($requested) && $amount > (float) $requested) {
                $validator->errors()->add(
                    'amount',
                    'The granted amount cannot exceed the requested amount of ₱'.number_format((float) $requested, 2).'.'
                );
            }
        });
    }

    /** Submitted granted amount as a float, or null when blank / not numeric. */
    private function amountValue(): ?float
    {
        $amount = $this->input('amount');

        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return null;
        }

        return (float) $amount;
    }
}
