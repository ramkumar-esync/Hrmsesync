<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Exception;

use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Shared\Domain\Exception\EntityNotFound;

final class PayrollRunNotFound extends EntityNotFound
{
    public static function withId(PayrollRunId $id): self
    {
        return new self("No payroll run found with id {$id}.");
    }
}
