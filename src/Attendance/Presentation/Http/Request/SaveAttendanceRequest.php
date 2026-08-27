<?php

declare(strict_types=1);

namespace HR\Attendance\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class SaveAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'entries' => ['present', 'array'],
            'entries.*.date' => ['required', 'date_format:Y-m-d'],
            'entries.*.hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'entries.*.leave_type_code' => ['nullable', 'string', 'max:20'],
            'entries.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'entries.*.date.date_format' => 'Each row needs a valid date.',
            'entries.*.hours.max' => 'A day cannot have more than 24 hours.',
        ];
    }
}
