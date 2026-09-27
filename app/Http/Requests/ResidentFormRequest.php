<?php

namespace App\Http\Requests;

use App\Models\Household;
use Closure;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for creating and editing a resident record.
 *
 * The lifecycle status field is deliberately not trusted here: the controller
 * decides who may set Active / Archived (see ResidentController::store() and
 * ::update()). Staff may create and edit residents; only administrators may
 * archive or restore them.
 */
abstract class ResidentFormRequest extends FormRequest
{
    /**
     * Household statuses a resident may be assigned to.
     * Vacant households are not open for members.
     *
     * @var list<string>
     */
    public const ASSIGNABLE_HOUSEHOLD_STATUSES = ['Occupied', 'Under Construction'];

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('residents.manage') ?? false;
    }

    /** Trim text fields and turn blanks into null so `nullable` rules apply. */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach ([
            'first_name', 'middle_name', 'last_name', 'suffix', 'birth_date',
            'sex', 'civil_status', 'occupation', 'phone', 'email', 'address',
            'purok_id', 'household_id', 'status',
        ] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = trim((string) $this->input($field));
            $merge[$field] = $value === '' ? null : $value;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::in(Resident::SEXES)],
            'civil_status' => ['required', Rule::in(Resident::CIVIL_STATUSES)],
            'occupation' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^(?=.*\d)\+?[0-9()\-\s]+$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'purok_id' => ['nullable', 'integer', 'exists:puroks,id'],
            'household_id' => [
                'nullable',
                'integer',
                'exists:households,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $household = Household::find($value);

                    if ($household !== null && ! in_array($household->status, self::ASSIGNABLE_HOUSEHOLD_STATUSES, true)) {
                        $fail('The selected household is not open for members — its status must be Occupied or Under Construction.');
                    }
                },
            ],
            'status' => ['sometimes', Rule::in([Resident::STATUS_ACTIVE, Resident::STATUS_ARCHIVED])],
            'photo' => ['nullable', 'image', 'extensions:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => "The resident's first name is required.",
            'last_name.required' => "The resident's last name is required.",
            'birth_date.required' => "The resident's date of birth is required.",
            'birth_date.before_or_equal' => 'The date of birth cannot be in the future.',
            'sex.required' => 'Please select the sex of the resident.',
            'civil_status.required' => 'Please select a civil status.',
            'phone.regex' => 'Phone numbers must contain at least one digit and may only use +, parentheses, dashes and spaces.',
            'photo.image' => 'The photo must be an image file.',
            'photo.extensions' => 'The photo must be a JPG, JPEG, PNG or WebP file.',
            'photo.max' => 'The photo may not be larger than 2 MB.',
            'status.in' => 'Choose a valid resident status.',
        ];
    }
}
