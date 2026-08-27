<?php

declare(strict_types=1);

namespace HR\Employee\Domain\Repository;

use HR\Employee\Domain\Entity\Employee;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Employee\Domain\ValueObject\EmployeeNumber;

interface EmployeeRepository
{
    public function save(Employee $employee): void;

    public function find(EmployeeId $id): ?Employee;

    /** @throws \HR\Employee\Domain\Exception\EmployeeNotFound */
    public function get(EmployeeId $id): Employee;

    public function findByEmployeeNumber(EmployeeNumber $number): ?Employee;

    public function findByUserId(string $userId): ?Employee;

    public function employeeNumberExists(EmployeeNumber $number): bool;

    /** @return list<Employee> Everyone eligible to be paid for the given window. */
    public function activeDuring(\DateTimeImmutable $start, \DateTimeImmutable $end): array;

    /** @return list<EmployeeId> */
    public function directReportsOf(EmployeeId $managerId): array;
}
