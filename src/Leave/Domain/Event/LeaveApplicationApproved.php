<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Event;

use Carbon\CarbonImmutable;
use HR\Leave\Domain\ValueObject\LeaveApplicationId;
use HR\Shared\Domain\Event\DomainEvent;

final readonly class LeaveApplicationApproved implements DomainEvent
{
    public function __construct(
        public LeaveApplicationId $applicationId,
        public string $employeeId,
        public string $approverId,
        public float $workingDays,
        private CarbonImmutable $occurredAt,
    ) {}

    public function occurredAt(): CarbonImmutable
    {
        return $this->occurredAt;
    }
}
