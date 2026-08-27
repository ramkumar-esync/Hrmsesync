<?php

declare(strict_types=1);

namespace HR\Payroll\Presentation\Http\Request;

use HR\Payroll\Domain\ValueObject\PayComponentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordPayrollEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $earningTypes = array_map(
            static fn (PayComponentType $type) => $type->value,
            array_filter(PayComponentType::cases(), static fn ($type) => $type->isEarning()),
        );

        $manualDeductionTypes = array_map(
            static fn (PayComponentType $type) => $type->value,
            array_filter(
                PayComponentType::cases(),
                // Statutory lines are calculated, never typed in — except PCB,
                // which HR takes from their LHDN calculator.
                static fn ($type) => ! $type->isEarning()
                    && (! $type->isStatutory() || $type === PayComponentType::Pcb),
            ),
        );

        return [
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'use_contractual_salary' => ['boolean'],
            'remarks' => ['nullable', 'string', 'max:500'],

            'earnings' => ['array'],
            'earnings.*.type' => ['required', Rule::in($earningTypes)],
            'earnings.*.amount' => ['required', 'decimal:0,2', 'min:0'],
            'earnings.*.description' => ['nullable', 'string', 'max:120'],

            'deductions' => ['array'],
            'deductions.*.type' => ['required', Rule::in($manualDeductionTypes)],
            'deductions.*.amount' => ['required', 'decimal:0,2', 'min:0'],
            'deductions.*.description' => ['nullable', 'string', 'max:120'],
        ];
    }
}
