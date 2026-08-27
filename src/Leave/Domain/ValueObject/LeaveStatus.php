<?php

declare(strict_types=1);

namespace HR\Leave\Domain\ValueObject;

enum LeaveStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    /** Does this status hold days against the employee's balance? */
    public function reservesBalance(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }

    public function isFinal(): bool
    {
        return $this === self::Rejected || $this === self::Cancelled;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting approval',
            self::Approved => 'Approved',
            self::Rejected => 'Not approved',
            self::Cancelled => 'Cancelled',
        };
    }
}
