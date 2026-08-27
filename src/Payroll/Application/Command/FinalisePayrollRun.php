<?php

declare(strict_types=1);

namespace HR\Payroll\Application\Command;

final readonly class FinalisePayrollRun
{
    public function __construct(
        public string $runId,
        public string $finalisedBy,
    ) {}
}
