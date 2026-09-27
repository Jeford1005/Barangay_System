<?php

namespace App\Http\Requests;

use App\Models\Blotter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for recording and updating a blotter entry.
 *
 * The routes already sit behind permission:blotter.manage — authorize()
 * repeats the check so a stale tab can never write to the case sheet.
 * StoreBlotterRequest / UpdateBlotterRequest are thin concrete wrappers.
 */
abstract class BlotterFormRequest extends FormRequest
{
    /** Phone numbers: any allowed character plus at least one digit. */
    public const PHONE_REGEX = '/^(?=.*\d)\+?[0-9()\-\s]+$/';

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('blotter.manage') ?? false;
    }

    /** Trim every text field and turn "" into null for the nullable ones. */
    protected function prepareForValidation(): void
    {
        $textFields = [
            'incident_date', 'incident_time', 'incident_type', 'location',
            'complainant_name', 'complainant_contact', 'respondent_name',
            'respondent_contact', 'narrative', 'handling_officer', 'resolution_notes',
        ];

        $nullable = [
            'incident_time', 'complainant_contact', 'respondent_name',
            'respondent_contact', 'handling_officer', 'resolution_notes',
        ];

        $merge = [];

        foreach ($textFields as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            $merge[$field] = ($value === '' && in_array($field, $nullable, true)) ? null : $value;
        }

        if ($this->input('purok_id') === '') {
            $merge['purok_id'] = null;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'incident_date' => ['required', 'date', 'before_or_equal:today'],
            'incident_time' => ['nullable', 'date_format:H:i'],
            'incident_type' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:255'],
            'purok_id' => ['nullable', 'integer', 'exists:puroks,id'],
            'complainant_name' => ['required', 'string', 'max:150'],
            'complainant_contact' => ['nullable', 'string', 'max:30', 'regex:'.self::PHONE_REGEX],
            'respondent_name' => ['nullable', 'string', 'max:150'],
            'respondent_contact' => ['nullable', 'string', 'max:30', 'regex:'.self::PHONE_REGEX],
            'narrative' => ['required', 'string', 'max:5000'],
            'handling_officer' => ['nullable', 'string', 'max:150'],
            'arrest_made' => ['required', 'in:Yes,No'],
            'status' => ['required', 'string', Rule::in(Blotter::STATUSES)],
            'resolution_notes' => [
                'nullable', 'string', 'max:5000',
                'required_if:status,Resolved', 'required_if:status,Dismissed',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'incident_date.before_or_equal' => 'The incident date cannot be in the future.',
            'incident_time.date_format' => 'The incident time must be a valid time (HH:MM).',
            'complainant_contact.regex' => 'The complainant contact may only contain digits, spaces, +, (, ) and dashes — and must include at least one digit.',
            'respondent_contact.regex' => 'The respondent contact may only contain digits, spaces, +, (, ) and dashes — and must include at least one digit.',
            'resolution_notes.required_if' => 'Resolution notes are required when the status is Resolved or Dismissed.',
            'arrest_made.in' => 'Please choose whether an arrest was made.',
            'status.in' => 'Please choose a valid case status.',
        ];
    }
}
