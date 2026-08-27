<?php

declare(strict_types=1);

namespace HR\Payroll\Application\Command;

final readonly class MarkPayrollRunPaid
{
    public function __construct(public string $runId, public ?string $paidAt = null) {}
}
