<?php

namespace App\Http\Requests\Certificates;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Administrator voiding an issued certificate (route is admin-only too).
 *
 * The hidden "row" field only tells the index view which dialog to reopen
 * after a validation error — it is never trusted for authorization.
 */
class VoidCertificateRequest extends FormRequest
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
            'void_reason' => ['nullable', 'string', 'max:255'],
            'row' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'void_reason.string' => 'The reason must be text.',
            'void_reason.max' => 'The reason may be at most 255 characters.',
        ];
    }
}
