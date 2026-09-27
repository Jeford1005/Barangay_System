<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for creating and editing a purok.
 *
 * Writing puroks is administrator-only: the routes sit behind the `admin`
 * middleware and authorize() repeats the check inline.
 */
abstract class PurokFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** Trim the text fields and normalise blanks to null before validating. */
    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $code = trim((string) $this->input('code'));
        $description = trim((string) $this->input('description'));

        $this->merge([
            'name' => $name === '' ? null : $name,
            'code' => $code === '' ? null : strtoupper($code),
            'description' => $description === '' ? null : $description,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('puroks', 'name')->ignore($this->route('purok')),
            ],
            'code' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('puroks', 'code')->ignore($this->route('purok')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please give the purok a name.',
            'name.max' => 'Purok names may not be longer than 50 characters.',
            'name.unique' => 'A purok with that name already exists.',
            'code.max' => 'Codes may not be longer than 10 characters.',
            'code.unique' => 'A purok with that code already exists.',
            'description.max' => 'Descriptions may not be longer than 255 characters.',
        ];
    }
}
