<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Exception;

use HR\Payroll\Domain\ValueObject\PayslipId;
use HR\Shared\Domain\Exception\EntityNotFound;

final class PayslipNotFound extends EntityNotFound
{
    public static function withId(PayslipId $id): self
    {
        return new self("No payslip found with id {$id}.");
    }
}
