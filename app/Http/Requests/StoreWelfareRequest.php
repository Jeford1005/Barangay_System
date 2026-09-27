<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

/** Staff taking in an assistance request — it always starts as "Requested". */
class StoreWelfareRequest extends WelfareRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('welfare.intake') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->commonRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->checkResident($validator);
    }
}
