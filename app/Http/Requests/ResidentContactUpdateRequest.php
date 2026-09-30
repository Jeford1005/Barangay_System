<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResidentContactUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only residents may use the portal endpoint; the route is scoped to
        // the authenticated user's own record in the controller.
        return $this->user() !== null && $this->user()->user_type === 'resident';
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'phone_number' => ['nullable', 'string', 'max:15', 'regex:/^(?=.*\d)\+?[0-9()\-\s]+$/'],
            'address' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            'email' => [
                'nullable',
                'email',
                'max:150',
                'confirmed',
                \Illuminate\Validation\Rule::unique('users', 'email')->ignore($userId),
            ],
            'email_confirmation' => ['nullable', 'email', 'max:150'],
            'current_password' => ['nullable', 'required_with:email', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.regex' => 'The phone number must contain at least one digit. Spaces, +, -, and parentheses are allowed.',
        ];
    }
}
