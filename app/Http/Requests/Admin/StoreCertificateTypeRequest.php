<?php

namespace App\Http\Requests\Admin;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Administrator adding a certificate type to the catalog. */
class StoreCertificateTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $code = strtoupper(trim((string) $this->input('code')));
        $title = trim((string) $this->input('title'));
        $description = trim((string) $this->input('description'));

        $this->merge([
            'code' => $code,
            'title' => $title,
            'description' => $description === '' ? null : $description,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]{2,8}$/', Rule::unique('documents', 'code')],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'document_type' => ['required', 'string', Rule::in(Document::TYPES)],
            'fee' => ['required', 'numeric', 'min:0', 'max:9999'],
            'status' => ['required', 'string', Rule::in(Document::STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Give the certificate type a short code.',
            'code.regex' => 'The code must be 2 to 8 uppercase letters or numbers, like CLR or IND.',
            'code.unique' => 'That code is already used by another certificate type.',
            'title.required' => 'Give the certificate type a title.',
            'document_type.in' => 'Choose a valid document type.',
            'fee.required' => 'Enter the fee (use 0 for a free certificate).',
            'fee.numeric' => 'The fee must be an amount.',
            'fee.min' => 'The fee cannot be negative.',
            'fee.max' => 'The fee cannot be more than ₱9,999.00.',
            'status.in' => 'Choose a valid status.',
        ];
    }
}
