<?php

namespace App\Http\Requests\Admin;

use App\Models\Official;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Administrator adding a barangay official to the roster.
 *
 * `position` is free text with suggestions (a datalist), so it is only
 * length-checked — the seeded options are a convenience, not a whitelist.
 */
class StoreOfficialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $trim = fn (mixed $value): ?string => ($value === null || trim((string) $value) === '')
            ? null
            : trim((string) $value);

        $this->merge([
            'full_name' => $trim($this->input('full_name')) ?? '',
            'position' => $trim($this->input('position')) ?? '',
            'term_start' => $trim($this->input('term_start')) ?? '',
            'term_end' => $trim($this->input('term_end')) ?? '',
            'contact' => $trim($this->input('contact')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'position' => ['required', 'string', 'max:100'],
            'term_start' => ['required', 'date'],
            'term_end' => ['required', 'date', 'after_or_equal:term_start'],
            'contact' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in([Official::STATUS_ACTIVE, Official::STATUS_INACTIVE])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.max' => 'The full name may not be longer than 150 characters.',
            'position.required' => 'Enter the position held, or pick one of the suggested positions.',
            'position.max' => 'The position may not be longer than 100 characters.',
            'term_start.required' => 'Enter the first day of the term.',
            'term_start.date' => 'Enter a valid term start date.',
            'term_end.required' => 'Enter the last day of the term.',
            'term_end.date' => 'Enter a valid term end date.',
            'term_end.after_or_equal' => 'The term end date cannot be earlier than the term start date.',
            'contact.max' => 'The contact number may not be longer than 30 characters.',
            'status.in' => 'Choose a valid status — Active or Inactive.',
        ];
    }
}
