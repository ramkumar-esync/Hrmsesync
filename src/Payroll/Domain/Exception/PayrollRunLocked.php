<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Exception;

use HR\Payroll\Domain\ValueObject\PayrollRunStatus;
use HR\Shared\Domain\Exception\DomainException;

final class PayrollRunLocked extends DomainException
{
    public static function inState(PayrollRunStatus $status): self
    {
        return new self(
            "This payroll run is {$status->value} and can no longer be changed. "
            .'Open a new run for adjustments.'
        );
    }

    public function statusCode(): int
    {
        return 409;
    }
}
