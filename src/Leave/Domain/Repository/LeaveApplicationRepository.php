<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Repository;

use HR\Leave\Domain\Entity\LeaveApplication;
use HR\Leave\Domain\ValueObject\LeaveApplicationId;
use HR\Shared\Domain\ValueObject\DateRange;

interface LeaveApplicationRepository
{
    public function save(LeaveApplication $application): void;

    public function find(LeaveApplicationId $id): ?LeaveApplication;

    /** @throws \HR\Leave\Domain\Exception\LeaveApplicationNotFound */
    public function get(LeaveApplicationId $id): LeaveApplication;

    /** @return list<LeaveApplication> Pending or approved applications clashing with these dates. */
    public function activeOverlapping(string $employeeId, DateRange $dates): array;

    /** @return list<LeaveApplication> */
    public function pendingForApprover(string $approverEmployeeId): array;
}
