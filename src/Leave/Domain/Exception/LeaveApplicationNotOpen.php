<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Exception;

use HR\Leave\Domain\ValueObject\LeaveStatus;
use HR\Shared\Domain\Exception\DomainException;

final class LeaveApplicationNotOpen extends DomainException
{
    public static function inStatus(LeaveStatus $status): self
    {
        return new self("This application is already {$status->value} and cannot be changed.");
    }

    public function statusCode(): int
    {
        return 409;
    }
}
