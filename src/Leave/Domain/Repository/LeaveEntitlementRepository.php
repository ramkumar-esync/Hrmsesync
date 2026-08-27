<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Repository;

use HR\Leave\Domain\Entity\LeaveEntitlement;
use HR\Leave\Domain\ValueObject\LeaveEntitlementId;
use HR\Leave\Domain\ValueObject\LeaveTypeId;

interface LeaveEntitlementRepository
{
    public function save(LeaveEntitlement $entitlement): void;

    public function find(LeaveEntitlementId $id): ?LeaveEntitlement;

    public function findFor(string $employeeId, LeaveTypeId $leaveTypeId, int $year): ?LeaveEntitlement;

    /**
     * Same as findFor, but takes a row lock so two concurrent applications
     * cannot both pass the balance check against the same remaining day.
     */
    public function findForUpdate(string $employeeId, LeaveTypeId $leaveTypeId, int $year): ?LeaveEntitlement;

    /** @return list<LeaveEntitlement> */
    public function allForEmployee(string $employeeId, int $year): array;
}
