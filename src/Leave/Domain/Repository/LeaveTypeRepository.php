<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Repository;

use HR\Leave\Domain\Entity\LeaveType;
use HR\Leave\Domain\ValueObject\LeaveTypeId;

interface LeaveTypeRepository
{
    public function save(LeaveType $type): void;

    public function find(LeaveTypeId $id): ?LeaveType;

    /** @throws \HR\Leave\Domain\Exception\LeaveTypeNotFound */
    public function get(LeaveTypeId $id): LeaveType;

    public function findByCode(string $code): ?LeaveType;

    /** @return list<LeaveType> */
    public function allActive(): array;
}
