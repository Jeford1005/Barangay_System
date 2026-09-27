<?php

namespace App\Http\Requests\CertificateRequests;

use Illuminate\Foundation\Http\FormRequest;

/** Administrator rejecting an online certificate request. */
class RejectRequest extends FormRequest
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
            'rejection_reason' => ['required', 'string', 'max:1000'],
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
            'rejection_reason.required' => 'Tell the resident why the request is rejected.',
            'rejection_reason.max' => 'The reason may be at most 1,000 characters.',
        ];
    }
}
