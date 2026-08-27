<?php

declare(strict_types=1);

namespace HR\Employee\Application\Command;

use HR\Employee\Domain\Entity\Employee;
use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\Compensation;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Shared\Domain\ValueObject\Money;

final readonly class UpdateCompensationHandler
{
    public function __construct(private EmployeeRepository $employees) {}

    public function __invoke(UpdateCompensation $command): Employee
    {
        $employee = $this->employees->get(EmployeeId::fromString($command->employeeId));

        $employee->changeCompensation(new Compensation(
            basicSalary: Money::fromDecimal($command->basicSalary),
            frequency: $employee->compensation()->frequency,
            fixedAllowance: $command->fixedAllowance !== null
                ? Money::fromDecimal($command->fixedAllowance)
                : null,
        ));

        $this->employees->save($employee);

        return $employee;
    }
}
