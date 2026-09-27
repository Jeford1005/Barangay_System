<?php

namespace App\Http\Requests\CertificateRequests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Administrator approving an online certificate request.
 *
 * The fee is an optional override (0 – 9999); leaving it blank means the
 * catalog fee of the certificate type applies.
 */
class ApproveRequest extends FormRequest
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
        return [
            'fee' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            // Dialog bookkeeping: tells the index view which dialog to reopen
            // after a validation error.
            'row' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fee.numeric' => 'The fee must be an amount.',
            'fee.min' => 'The fee cannot be negative.',
            'fee.max' => 'The fee cannot be more than ₱9,999.00.',
        ];
    }
}
