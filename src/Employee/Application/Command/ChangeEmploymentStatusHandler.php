<?php

declare(strict_types=1);

namespace HR\Employee\Application\Command;

use Carbon\CarbonImmutable;
use HR\Employee\Domain\Entity\Employee;
use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Employee\Domain\ValueObject\EmploymentStatus;
use HR\Shared\Application\TransactionManager;

final readonly class ChangeEmploymentStatusHandler
{
    public function __construct(
        private EmployeeRepository $employees,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(ChangeEmploymentStatus $command): Employee
    {
        return $this->transaction->transactional(function () use ($command): Employee {
            $employee = $this->employees->get(EmployeeId::fromString($command->employeeId));

            $employee->changeStatus(
                EmploymentStatus::from($command->status),
                $command->effectiveOn !== null
                    ? CarbonImmutable::parse($command->effectiveOn)
                    : null,
            );

            $this->employees->save($employee);

            return $employee;
        });
    }
}
