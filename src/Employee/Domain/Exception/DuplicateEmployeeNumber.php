<?php

declare(strict_types=1);

namespace HR\Employee\Domain\Exception;

use HR\Employee\Domain\ValueObject\EmployeeNumber;
use HR\Shared\Domain\Exception\DomainException;

final class DuplicateEmployeeNumber extends DomainException
{
    public static function of(EmployeeNumber $number): self
    {
        return new self("Employee number {$number} is already in use.");
    }

    public function statusCode(): int
    {
        return 409;
    }
}
