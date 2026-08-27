<?php

declare(strict_types=1);

namespace HR\Leave\Application\Command;

final readonly class AdjustEntitlement
{
    public function __construct(
        public string $employeeId,
        public string $leaveTypeId,
        public int $year,
        public float $days,
        public string $reason,
    ) {}
}
