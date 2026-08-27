<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Exception;

use HR\Shared\Domain\Exception\DomainException;

final class DuplicatePayslip extends DomainException
{
    public static function forEmployee(string $employeeNumber): self
    {
        return new self("Employee {$employeeNumber} already has a payslip in this run.");
    }

    public function statusCode(): int
    {
        return 409;
    }
}
