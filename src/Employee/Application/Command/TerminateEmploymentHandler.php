<?php

declare(strict_types=1);

namespace HR\Employee\Application\Command;

use Carbon\CarbonImmutable;
use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Employee\Domain\ValueObject\EmploymentStatus;
use HR\Identity\Infrastructure\Persistence\Eloquent\User;
use HR\Shared\Application\TransactionManager;

final readonly class TerminateEmploymentHandler
{
    public function __construct(
        private EmployeeRepository $employees,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(TerminateEmployment $command): void
    {
        $this->transaction->transactional(function () use ($command): void {
            $employee = $this->employees->get(EmployeeId::fromString($command->employeeId));

            $employee->terminate(
                CarbonImmutable::parse($command->lastDay),
                EmploymentStatus::from($command->reason),
            );

            $this->employees->save($employee);

            // Past payslips stay downloadable; the account simply stops working.
            if ($employee->userId() !== null) {
                User::query()->whereKey($employee->userId())->update(['is_active' => false]);
            }
        });
    }
}
