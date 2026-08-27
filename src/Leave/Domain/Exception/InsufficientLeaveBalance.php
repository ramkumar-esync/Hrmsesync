<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Exception;

use HR\Shared\Domain\Exception\DomainException;

final class InsufficientLeaveBalance extends DomainException
{
    public static function needing(float $requested, float $available): self
    {
        return new self(sprintf(
            'You have %s day%s available and this request needs %s.',
            rtrim(rtrim(number_format($available, 1), '0'), '.'),
            $available == 1.0 ? '' : 's',
            rtrim(rtrim(number_format($requested, 1), '0'), '.'),
        ));
    }
}
