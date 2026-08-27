<?php

declare(strict_types=1);

namespace HR\Employee\Application\DTO;

/** Flat, validated input crossing from the HTTP layer into the application layer. */
final readonly class RegisterEmployeeData
{
    public function __construct(
        public string $employeeNumber,
        public string $name,
        public string $workEmail,
        public string $joinedOn,
        public string $jobTitle,
        public string $basicSalary,
        public string $dateOfBirth,
        public ?string $fixedAllowance = null,
        public ?string $department = null,
        public ?string $reportsTo = null,
        public bool $isCitizen = true,
        public bool $epfApplicable = true,
        public bool $socsoApplicable = true,
        public bool $eisApplicable = true,
        public ?string $epfNumber = null,
        public ?string $socsoNumber = null,
        public ?string $taxReferenceNumber = null,
        public ?string $nationalIdNumber = null,
        public ?string $bankName = null,
        public ?string $bankAccountNumber = null,
        public bool $createLoginAccount = true,
        public ?string $role = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            employeeNumber: (string) $data['employee_number'],
            name: (string) $data['name'],
            workEmail: (string) $data['work_email'],
            joinedOn: (string) $data['joined_on'],
            jobTitle: (string) $data['job_title'],
            basicSalary: (string) $data['basic_salary'],
            dateOfBirth: (string) $data['date_of_birth'],
            fixedAllowance: isset($data['fixed_allowance']) ? (string) $data['fixed_allowance'] : null,
            department: $data['department'] ?? null,
            reportsTo: $data['reports_to'] ?? null,
            isCitizen: (bool) ($data['is_citizen'] ?? true),
            epfApplicable: (bool) ($data['epf_applicable'] ?? true),
            socsoApplicable: (bool) ($data['socso_applicable'] ?? true),
            eisApplicable: (bool) ($data['eis_applicable'] ?? true),
            epfNumber: $data['epf_number'] ?? null,
            socsoNumber: $data['socso_number'] ?? null,
            taxReferenceNumber: $data['tax_reference_number'] ?? null,
            nationalIdNumber: $data['national_id_number'] ?? null,
            bankName: $data['bank_name'] ?? null,
            bankAccountNumber: $data['bank_account_number'] ?? null,
            createLoginAccount: (bool) ($data['create_login_account'] ?? true),
            role: $data['role'] ?? null,
        );
    }
}
