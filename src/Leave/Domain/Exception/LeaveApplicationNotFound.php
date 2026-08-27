<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Exception;

use HR\Leave\Domain\ValueObject\LeaveApplicationId;
use HR\Shared\Domain\Exception\EntityNotFound;

final class LeaveApplicationNotFound extends EntityNotFound
{
    public static function withId(LeaveApplicationId $id): self
    {
        return new self("No leave application found with id {$id}.");
    }
}
