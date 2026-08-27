<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\ValueObject;

use HR\Employee\Domain\Entity\Employee;

/**
 * Employee details frozen at the moment the payslip was issued.
 *
 * A payslip is a historical record. If someone changes department or bank
 * account next year, last year's payslip must still show what was true then —
 * so the payslip copies these fields instead of joining to the live record.
 */
final readonly class EmployeeSnapshot implements \JsonSerializable
{
    public function __construct(
        public string $employeeId,
        public string $employeeNumber,
        public string $name,
        public string $jobTitle,
        public ?string $department,
        public ?string $epfNumber,
        public ?string $socsoNumber,
        public ?string $taxReferenceNumber,
        public ?string $bankName,
        public ?string $bankAccountMasked,
    ) {}

    public static function fromEmployee(Employee $employee): self
    {
        return new self(
            employeeId: $employee->id->value,
            employeeNumber: $employee->employeeNumber()->value,
            name: $employee->name()->full,
            jobTitle: $employee->jobTitle(),
            department: $employee->department(),
            epfNumber: $employee->statutoryProfile()->epfNumber,
            socsoNumber: $employee->statutoryProfile()->socsoNumber,
            taxReferenceNumber: $employee->statutoryProfile()->taxReferenceNumber,
            bankName: $employee->bankAccount()?->bankName,
            bankAccountMasked: $employee->bankAccount()?->masked(),
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            employeeId: (string) $data['employee_id'],
            employeeNumber: (string) $data['employee_number'],
            name: (string) $data['name'],
            jobTitle: (string) $data['job_title'],
            department: $data['department'] ?? null,
            epfNumber: $data['epf_number'] ?? null,
            socsoNumber: $data['socso_number'] ?? null,
            taxReferenceNumber: $data['tax_reference_number'] ?? null,
            bankName: $data['bank_name'] ?? null,
            bankAccountMasked: $data['bank_account_masked'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'employee_number' => $this->employeeNumber,
            'name' => $this->name,
            'job_title' => $this->jobTitle,
            'department' => $this->department,
            'epf_number' => $this->epfNumber,
            'socso_number' => $this->socsoNumber,
            'tax_reference_number' => $this->taxReferenceNumber,
            'bank_name' => $this->bankName,
            'bank_account_masked' => $this->bankAccountMasked,
        ];
    }
}
