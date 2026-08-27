<?php

declare(strict_types=1);

namespace HR\Leave\Application\Command;

final readonly class GrantAnnualEntitlements
{
    public function __construct(public int $year, public ?string $employeeId = null) {}
}
