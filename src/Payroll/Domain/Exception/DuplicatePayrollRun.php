<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Exception;

use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Shared\Domain\Exception\DomainException;

final class DuplicatePayrollRun extends DomainException
{
    public static function forPeriod(PayPeriod $period): self
    {
        return new self("A payroll run for {$period->label()} already exists.");
    }

    public function statusCode(): int
    {
        return 409;
    }
}
