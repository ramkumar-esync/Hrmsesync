<?php

declare(strict_types=1);

namespace HR\Payroll\Application\Command;

final readonly class OpenPayrollRun
{
    public function __construct(
        public string $period,
        public string $paymentDate,
        public string $openedBy,
        public ?string $notes = null,
    ) {}
}
