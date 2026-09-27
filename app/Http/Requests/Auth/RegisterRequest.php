<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Resident self-registration (dialog on the sign-in page).
 *
 * Field names are prefixed with "reg_" so a registration error can never
 * paint itself under the sign-in form's own email/password fields.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guests only; the role is forced to "resident"
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reg_name' => ['required', 'string', 'min:3', 'max:150'],
            'reg_email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email'),
            ],
            'reg_password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reg_name.required' => 'Please enter your full name.',
            'reg_name.min' => 'Please enter your full name (at least 3 characters).',
            'reg_email.required' => 'Please enter your email address.',
            'reg_email.email' => 'Please enter a valid email address.',
            'reg_email.unique' => 'An account with this email already exists. Try signing in instead.',
            'reg_password.required' => 'Please choose a password.',
            'reg_password.min' => 'The password must be at least 8 characters.',
            'reg_password.confirmed' => 'The passwords do not match.',
        ];
    }
}
