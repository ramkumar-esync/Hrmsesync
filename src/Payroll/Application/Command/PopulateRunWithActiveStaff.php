<?php

declare(strict_types=1);

namespace HR\Payroll\Application\Command;

final readonly class PopulateRunWithActiveStaff
{
    public function __construct(public string $runId) {}
}
