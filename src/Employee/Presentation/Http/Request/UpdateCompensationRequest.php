<?php

declare(strict_types=1);

namespace HR\Employee\Presentation\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCompensationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'basic_salary' => ['required', 'decimal:0,2', 'min:0'],
            'fixed_allowance' => ['nullable', 'decimal:0,2', 'min:0'],
        ];
    }
}
