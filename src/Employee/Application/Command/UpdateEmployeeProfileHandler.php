<?php

declare(strict_types=1);

namespace HR\Employee\Application\Command;

use HR\Employee\Domain\Entity\Employee;
use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\BankAccount;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Employee\Domain\ValueObject\PersonName;

final readonly class UpdateEmployeeProfileHandler
{
    public function __construct(private EmployeeRepository $employees) {}

    public function __invoke(UpdateEmployeeProfile $command): Employee
    {
        $employee = $this->employees->get(EmployeeId::fromString($command->employeeId));

        // Identity + role. Fall back to the current value for anything omitted,
        // so a blank field means "leave as is" rather than "clear".
        $employee->updateProfile(
            name: new PersonName($command->name ?? $employee->name()->full),
            jobTitle: $command->jobTitle ?? $employee->jobTitle(),
            department: $command->department ?? $employee->department(),
        );

        // Bank account is optional; only rewrite it when a number is given.
        if ($command->bankAccountNumber !== null && $command->bankAccountNumber !== '') {
            $employee->updateBankAccount(new BankAccount(
                bankName: $command->bankName ?? $employee->bankAccount()?->bankName ?? '',
                accountNumber: $command->bankAccountNumber,
                accountHolder: $employee->name()->full,
            ));
        }

        $this->employees->save($employee);

        return $employee;
    }
}
