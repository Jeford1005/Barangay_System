<?php

namespace App\Http\Requests\Portal;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Resident submitting an online certificate / clearance request (POST /my/requests).
 *
 * Only Active catalog documents of type Certificate or Clearance may be asked
 * for online; anything else has to go through the barangay office counter.
 */
class StoreCertificateRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isResident() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'document_id' => ['required', 'integer', Rule::exists('documents', 'id')],
            'purpose' => ['required', 'string', 'max:255'],
            'copies' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }

    /** Business checks that go beyond the raw column rules. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('document_id')) {
                return;
            }

            $document = Document::query()->active()->find($this->input('document_id'));

            if ($document === null) {
                $validator->errors()->add(
                    'document_id',
                    'That document is not currently available for online requests.'
                );

                return;
            }

            if (! in_array($document->document_type, ['Certificate', 'Clearance'], true)) {
                $validator->errors()->add(
                    'document_id',
                    'Only certificates and clearances can be requested online. Please visit the barangay office for "'.$document->document_type.'" documents.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_id.required' => 'Choose the document you want to request.',
            'document_id.integer' => 'Choose the document you want to request.',
            'document_id.exists' => 'That document is no longer in the barangay catalog.',
            'purpose.required' => 'Tell us the purpose of your request.',
            'purpose.max' => 'The purpose can be at most 255 characters long.',
            'copies.required' => 'Choose how many copies you need.',
            'copies.integer' => 'Choose how many copies you need.',
            'copies.min' => 'You can request between 1 and 5 copies.',
            'copies.max' => 'You can request between 1 and 5 copies.',
        ];
    }
}
