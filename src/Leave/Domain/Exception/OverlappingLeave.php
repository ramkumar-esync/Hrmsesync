<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Exception;

use HR\Shared\Domain\Exception\DomainException;

final class OverlappingLeave extends DomainException
{
    public static function with(string $dates): self
    {
        return new self("You already have leave booked or pending for {$dates}.");
    }

    public function statusCode(): int
    {
        return 409;
    }
}
