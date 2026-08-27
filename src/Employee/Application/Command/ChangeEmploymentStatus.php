<?php

declare(strict_types=1);

namespace HR\Employee\Application\Command;

/**
 * HR moves an employee to a new employment status.
 *
 * effectiveOn matters only when the new status ends employment (resigned,
 * terminated), where it becomes the last working day. For a move between active
 * statuses it is ignored.
 */
final readonly class ChangeEmploymentStatus
{
    public function __construct(
        public string $employeeId,
        public string $status,
        public ?string $effectiveOn = null,
    ) {}
}
