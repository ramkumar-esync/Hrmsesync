<?php

declare(strict_types=1);

namespace HR\Payroll\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class OpenPayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'period.regex' => 'Use the format YYYY-MM, for example 2026-07.',
        ];
    }
}
