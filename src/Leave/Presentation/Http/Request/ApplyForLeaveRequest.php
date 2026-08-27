<?php

declare(strict_types=1);

namespace HR\Leave\Presentation\Http\Request;

use HR\Leave\Domain\ValueObject\DayPortion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ApplyForLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'uuid', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_portion' => ['sometimes', Rule::enum(DayPortion::class)],
            'end_portion' => ['sometimes', Rule::enum(DayPortion::class)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'contact_while_away' => ['nullable', 'string', 'max:120'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The last day cannot fall before the first day.',
            'reason.required' => 'Tell your approver why you need the time off.',
            'attachment.max' => 'Attachments must be 5 MB or smaller.',
        ];
    }
}
