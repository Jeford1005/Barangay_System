<?php

namespace App\Http\Requests\Admin;

use App\Models\Document;
use Illuminate\Validation\Rule;

/**
 * Administrator editing a certificate type.
 *
 * Same rules as creating, except the code must not collide with another
 * row. Once a type has already been issued, DocumentController silently
 * drops a posted code change so issued control numbers keep their meaning.
 */
class UpdateCertificateTypeRequest extends StoreCertificateTypeRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $document = $this->route('document');
        $ignoreId = $document instanceof Document ? $document->id : $document;

        $rules['code'] = [
            'required',
            'string',
            'max:10',
            'regex:/^[A-Z0-9]{2,8}$/',
            Rule::unique('documents', 'code')->ignore($ignoreId),
        ];

        return $rules;
    }
}
