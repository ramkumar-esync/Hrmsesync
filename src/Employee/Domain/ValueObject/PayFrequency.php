<?php

declare(strict_types=1);

namespace HR\Employee\Domain\ValueObject;

enum PayFrequency: string
{
    case Monthly = 'monthly';
    case Weekly = 'weekly';
    case Daily = 'daily';

    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Monthly => 12,
            self::Weekly => 52,
            self::Daily => 260,
        };
    }
}
