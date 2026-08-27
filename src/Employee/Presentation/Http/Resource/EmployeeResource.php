<?php

declare(strict_types=1);

namespace HR\Employee\Presentation\Http\Resource;

use HR\Employee\Domain\Entity\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EmployeeResource extends JsonResource
{
    public function __construct(private readonly Employee $employee)
    {
        parent::__construct($employee);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $employee = $this->employee;

        return [
            'id' => $employee->id->value,
            'employee_number' => $employee->employeeNumber()->value,
            'name' => $employee->name()->full,
            'work_email' => $employee->workEmail(),
            'job_title' => $employee->jobTitle(),
            'department' => $employee->department(),
            'status' => $employee->status()->value,
            'status_label' => $employee->status()->label(),
            'joined_on' => $employee->joinedOn()->toDateString(),
            'left_on' => $employee->leftOn()?->toDateString(),
            'reports_to' => $employee->reportsTo()?->value,
            'has_login' => $employee->userId() !== null,
            'compensation' => [
                'basic_salary' => $employee->compensation()->basicSalary,
                'fixed_allowance' => $employee->compensation()->fixedAllowance,
                'frequency' => $employee->compensation()->frequency->value,
            ],
            'bank_account' => $employee->bankAccount() === null ? null : [
                'bank_name' => $employee->bankAccount()->bankName,
                'account_number' => $employee->bankAccount()->masked(),
            ],
        ];
    }
}
