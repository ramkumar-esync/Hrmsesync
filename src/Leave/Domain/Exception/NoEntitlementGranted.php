<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Exception;

use HR\Shared\Domain\Exception\DomainException;

final class NoEntitlementGranted extends DomainException
{
    public static function forYear(string $leaveType, int $year): self
    {
        return new self(
            "No {$leaveType} entitlement has been granted for {$year}. Ask HR to set it up."
        );
    }
}
