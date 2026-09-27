<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Resident updating their own contact details from the portal (PUT /my/contact).
 *
 * An email change is written to both the resident record and the signing-in
 * account, so it must be free on each table (each ignoring the caller's row).
 */
class UpdateContactRequest extends FormRequest
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
        $resident = $this->user()?->linkedResident();

        return [
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^(?=.*\d)[0-9+()\. \-]*$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:150',
                Rule::unique('residents', 'email')->ignore($resident?->id),
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a phone number containing at least one digit, for example 0917 123 4567.',
            'phone.max' => 'Phone numbers can be at most 30 characters long.',
            'address.max' => 'The address can be at most 255 characters long.',
            'email.email' => 'Enter a valid email address.',
            'email.max' => 'The email can be at most 150 characters long.',
            'email.unique' => 'That email address is already used by another account or resident.',
        ];
    }
}
