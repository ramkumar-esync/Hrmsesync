<?php

declare(strict_types=1);

namespace HR\Employee\Domain\ValueObject;

use HR\Shared\Domain\Exception\InvariantViolation;

final readonly class PersonName implements \Stringable
{
    public string $full;

    public function __construct(string $full)
    {
        $full = preg_replace('/\s+/u', ' ', trim($full)) ?? '';

        if (mb_strlen($full) < 2) {
            throw InvariantViolation::because('A name needs at least two characters.');
        }

        $this->full = $full;
    }

    public function initials(): string
    {
        $parts = explode(' ', $this->full);

        return mb_strtoupper(implode('', array_map(
            static fn (string $part) => mb_substr($part, 0, 1),
            array_slice($parts, 0, 2),
        )));
    }

    public function __toString(): string
    {
        return $this->full;
    }
}
