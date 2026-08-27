<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Exception;

use HR\Shared\Domain\Exception\EntityNotFound;

final class LeaveTypeNotFound extends EntityNotFound
{
    public static function withCode(string $code): self
    {
        return new self("No leave type found for \"{$code}\".");
    }
}
