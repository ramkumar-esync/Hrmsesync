<?php

declare(strict_types=1);

namespace HR\Employee\Domain\ValueObject;

use HR\Shared\Domain\Exception\InvariantViolation;

/** The human-facing staff number printed on payslips, e.g. "EMP-0042". */
final readonly class EmployeeNumber implements \JsonSerializable, \Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        $value = strtoupper(trim($value));

        if (! preg_match('/^[A-Z0-9][A-Z0-9\-\/]{1,19}$/', $value)) {
            throw InvariantViolation::because(
                'An employee number must be 2–20 characters of letters, digits, hyphens or slashes.'
            );
        }

        $this->value = $value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
