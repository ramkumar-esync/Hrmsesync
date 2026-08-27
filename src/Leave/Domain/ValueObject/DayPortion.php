<?php

declare(strict_types=1);

namespace HR\Leave\Domain\ValueObject;

enum DayPortion: string
{
    case Full = 'full';
    case FirstHalf = 'first_half';
    case SecondHalf = 'second_half';

    public function days(): float
    {
        return $this === self::Full ? 1.0 : 0.5;
    }

    public function isHalf(): bool
    {
        return $this !== self::Full;
    }

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full day',
            self::FirstHalf => 'Morning',
            self::SecondHalf => 'Afternoon',
        };
    }
}
