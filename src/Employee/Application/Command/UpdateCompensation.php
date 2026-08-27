<?php

declare(strict_types=1);

namespace HR\Employee\Application\Command;

final readonly class UpdateCompensation
{
    public function __construct(
        public string $employeeId,
        public string $basicSalary,
        public ?string $fixedAllowance = null,
    ) {}
}
