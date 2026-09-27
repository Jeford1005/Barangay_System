<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Administrator creating a staff / official / resident account.
 */
class StoreAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email'),
            ],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'role' => ['required', Rule::in([User::ROLE_STAFF, User::ROLE_ADMIN, User::ROLE_RESIDENT])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'Choose a valid account role.',
        ];
    }
}
