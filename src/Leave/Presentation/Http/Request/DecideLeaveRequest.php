<?php

declare(strict_types=1);

namespace HR\Leave\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class DecideLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject'],
            // A rejection without a reason is not a decision the employee can act on.
            'note' => [
                'nullable',
                'required_if:decision,reject',
                'string',
                'min:3',
                'max:500',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'note.required_if' => 'Give a reason so the employee knows why this was declined.',
        ];
    }
}
