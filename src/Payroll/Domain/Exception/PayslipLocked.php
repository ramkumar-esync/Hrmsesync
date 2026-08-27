<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Exception;

use HR\Shared\Domain\Exception\DomainException;

final class PayslipLocked extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function statusCode(): int
    {
        return 409;
    }
}
