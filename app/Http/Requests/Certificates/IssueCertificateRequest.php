<?php

namespace App\Http\Requests\Certificates;

use App\Models\Document;
use App\Models\Resident;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Office staff / admin issuing a certificate over the counter.
 *
 * The catalog fee is authoritative: only administrators may post a fee
 * override, and CertificateController::store() ignores the posted value for
 * everyone else.
 */
class IssueCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('certificates.issue') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'document_id' => [
                'required',
                'integer',
                Rule::exists('documents', 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $document = Document::find((int) $value);

                    if ($document === null) {
                        return;
                    }

                    if ($document->status !== 'Active') {
                        $fail('That certificate type is not active. Choose an active certificate or clearance.');
                    } elseif (! in_array($document->document_type, ['Certificate', 'Clearance'], true)) {
                        $fail('Only certificates and clearances can be issued at this counter.');
                    }
                },
            ],

            'resident_id' => [
                'required',
                'integer',
                Rule::exists('residents', 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $resident = Resident::find((int) $value);

                    if ($resident === null) {
                        return;
                    }

                    if ($resident->status !== Resident::STATUS_ACTIVE) {
                        $fail('Only active residents can be issued a certificate. Check the resident record first.');
                    }
                },
            ],

            'purpose' => ['required', 'string', 'max:255'],

            'copies' => ['required', 'integer', 'min:1', 'max:5'],

            // Admin-only override; posted by staff it is simply ignored.
            'fee' => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_id.required' => 'Choose the certificate to issue.',
            'document_id.exists' => 'Choose the certificate to issue from the list.',
            'resident_id.required' => 'Choose the resident who will receive the certificate.',
            'resident_id.exists' => 'Choose the resident who will receive the certificate from the list.',
            'purpose.required' => 'State the purpose of the certificate.',
            'purpose.max' => 'The purpose may be at most 255 characters.',
            'copies.required' => 'How many copies should be issued?',
            'copies.min' => 'Issue at least one (1) copy.',
            'copies.max' => 'At most five (5) copies may be issued at a time.',
            'fee.numeric' => 'The fee must be an amount.',
            'fee.min' => 'The fee cannot be negative.',
            'fee.max' => 'The fee cannot be more than ₱9,999.00.',
        ];
    }
}
