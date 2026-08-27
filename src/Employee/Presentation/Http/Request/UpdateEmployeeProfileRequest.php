<?php

declare(strict_types=1);

namespace HR\Employee\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateEmployeeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'job_title' => ['required', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:40'],
        ];
    }
}
