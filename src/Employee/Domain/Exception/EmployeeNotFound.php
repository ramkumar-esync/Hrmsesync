<?php

declare(strict_types=1);

namespace HR\Employee\Domain\Exception;

use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Shared\Domain\Exception\EntityNotFound;

final class EmployeeNotFound extends EntityNotFound
{
    public static function withId(EmployeeId $id): self
    {
        return new self("No employee found with id {$id}.");
    }
}
