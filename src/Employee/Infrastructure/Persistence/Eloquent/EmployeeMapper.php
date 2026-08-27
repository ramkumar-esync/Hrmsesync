<?php

declare(strict_types=1);

namespace HR\Employee\Infrastructure\Persistence\Eloquent;

use Carbon\CarbonImmutable;
use HR\Employee\Domain\Entity\Employee;
use HR\Employee\Domain\ValueObject\BankAccount;
use HR\Employee\Domain\ValueObject\Compensation;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Employee\Domain\ValueObject\EmployeeNumber;
use HR\Employee\Domain\ValueObject\EmploymentStatus;
use HR\Employee\Domain\ValueObject\PayFrequency;
use HR\Employee\Domain\ValueObject\PersonName;
use HR\Employee\Domain\ValueObject\StatutoryProfile;
use HR\Shared\Domain\ValueObject\Money;

/** Translates between the Employee aggregate and its database row. */
final class EmployeeMapper
{
    public function toDomain(EmployeeRecord $record): Employee
    {
        return Employee::reconstitute(
            id: EmployeeId::fromString($record->id),
            employeeNumber: new EmployeeNumber($record->employee_number),
            name: new PersonName($record->name),
            workEmail: $record->work_email,
            joinedOn: CarbonImmutable::parse($record->joined_on),
            leftOn: $record->left_on ? CarbonImmutable::parse($record->left_on) : null,
            status: EmploymentStatus::from($record->status),
            jobTitle: $record->job_title,
            department: $record->department,
            compensation: new Compensation(
                basicSalary: Money::of($record->basic_salary_minor, $record->currency),
                frequency: PayFrequency::from($record->pay_frequency),
                fixedAllowance: $record->fixed_allowance_minor !== null
                    ? Money::of($record->fixed_allowance_minor, $record->currency)
                    : null,
            ),
            statutoryProfile: new StatutoryProfile(
                dateOfBirth: CarbonImmutable::parse($record->date_of_birth),
                isCitizen: (bool) $record->is_citizen,
                epfApplicable: (bool) $record->epf_applicable,
                socsoApplicable: (bool) $record->socso_applicable,
                eisApplicable: (bool) $record->eis_applicable,
                epfNumber: $record->epf_number,
                socsoNumber: $record->socso_number,
                taxReferenceNumber: $record->tax_reference_number,
                nationalIdNumber: $record->national_id_number,
                taxDependants: (int) $record->tax_dependants,
                isMarried: (bool) $record->is_married,
            ),
            bankAccount: $record->bank_account_number
                ? new BankAccount($record->bank_name, $record->bank_account_number, $record->name)
                : null,
            reportsTo: $record->reports_to ? EmployeeId::fromString($record->reports_to) : null,
            userId: $record->user_id,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(Employee $employee): array
    {
        $compensation = $employee->compensation();
        $statutory = $employee->statutoryProfile();

        return [
            'id' => $employee->id->value,
            'employee_number' => $employee->employeeNumber()->value,
            'name' => $employee->name()->full,
            'work_email' => $employee->workEmail(),
            'joined_on' => $employee->joinedOn()->toDateString(),
            'left_on' => $employee->leftOn()?->toDateString(),
            'status' => $employee->status()->value,
            'job_title' => $employee->jobTitle(),
            'department' => $employee->department(),
            'currency' => $compensation->basicSalary->currency,
            'basic_salary_minor' => $compensation->basicSalary->minorUnits,
            'fixed_allowance_minor' => $compensation->fixedAllowance?->minorUnits,
            'pay_frequency' => $compensation->frequency->value,
            'date_of_birth' => $statutory->dateOfBirth->toDateString(),
            'is_citizen' => $statutory->isCitizen,
            'epf_applicable' => $statutory->epfApplicable,
            'socso_applicable' => $statutory->socsoApplicable,
            'eis_applicable' => $statutory->eisApplicable,
            'epf_number' => $statutory->epfNumber,
            'socso_number' => $statutory->socsoNumber,
            'tax_reference_number' => $statutory->taxReferenceNumber,
            'national_id_number' => $statutory->nationalIdNumber,
            'tax_dependants' => $statutory->taxDependants,
            'is_married' => $statutory->isMarried,
            'bank_name' => $employee->bankAccount()?->bankName,
            'bank_account_number' => $employee->bankAccount()?->accountNumber,
            'reports_to' => $employee->reportsTo()?->value,
            'user_id' => $employee->userId(),
        ];
    }
}
