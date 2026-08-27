<?php

declare(strict_types=1);

namespace HR\Shared\Domain\Exception;

/** An aggregate was asked to enter a state its own rules forbid. */
final class InvariantViolation extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
