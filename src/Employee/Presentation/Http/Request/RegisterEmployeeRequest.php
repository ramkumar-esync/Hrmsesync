<?php

declare(strict_types=1);

namespace HR\Employee\Presentation\Http\Request;

use HR\Identity\Domain\Enum\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegisterEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is already behind role:hr_admin.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_number' => ['required', 'string', 'max:20', 'unique:employees,employee_number'],
            'name' => ['required', 'string', 'max:150'],
            'work_email' => ['required', 'email', 'max:255', 'unique:employees,work_email'],
            'joined_on' => ['required', 'date'],
            'date_of_birth' => ['required', 'date', 'before:joined_on'],
            'job_title' => ['required', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'basic_salary' => ['required', 'decimal:0,2', 'min:0'],
            'fixed_allowance' => ['nullable', 'decimal:0,2', 'min:0'],
            'reports_to' => ['nullable', 'uuid', 'exists:employees,id'],
            'is_citizen' => ['boolean'],
            'epf_applicable' => ['boolean'],
            'socso_applicable' => ['boolean'],
            'eis_applicable' => ['boolean'],
            'epf_number' => ['nullable', 'string', 'max:30'],
            'socso_number' => ['nullable', 'string', 'max:30'],
            'tax_reference_number' => ['nullable', 'string', 'max:30'],
            'national_id_number' => ['nullable', 'string', 'max:30'],
            'bank_name' => ['nullable', 'required_with:bank_account_number', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'required_with:bank_name', 'digits_between:6,20'],
            'create_login_account' => ['boolean'],
            'role' => ['nullable', Rule::enum(Role::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'Date of birth must fall before the join date.',
            'employee_number.unique' => 'That employee number is already in use.',
        ];
    }
}
