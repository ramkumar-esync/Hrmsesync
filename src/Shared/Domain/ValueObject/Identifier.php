<?php

declare(strict_types=1);

namespace HR\Shared\Domain\ValueObject;

use HR\Shared\Domain\Exception\InvariantViolation;
use Illuminate\Support\Str;

/** Base class for UUID-backed aggregate identifiers. */
abstract readonly class Identifier implements \JsonSerializable, \Stringable
{
    final public function __construct(public string $value)
    {
        if (! Str::isUuid($value)) {
            throw InvariantViolation::because(
                static::class.' expects a UUID, received "'.$value.'".'
            );
        }
    }

    public static function generate(): static
    {
        return new static((string) Str::uuid7());
    }

    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function equals(self $other): bool
    {
        return static::class === $other::class && $this->value === $other->value;
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
