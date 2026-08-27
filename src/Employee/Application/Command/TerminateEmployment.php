<?php

declare(strict_types=1);

namespace HR\Employee\Application\Command;

final readonly class TerminateEmployment
{
    public function __construct(
        public string $employeeId,
        public string $lastDay,
        public string $reason = 'resigned',
    ) {}
}
