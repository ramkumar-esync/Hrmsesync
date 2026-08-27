<?php

declare(strict_types=1);

namespace HR\Employee\Domain\ValueObject;

enum EmploymentStatus: string
{
    case Probation = 'probation';
    case Confirmed = 'confirmed';
    case Contract = 'contract';
    case Resigned = 'resigned';
    case Terminated = 'terminated';

    public function isActive(): bool
    {
        return in_array($this, [self::Probation, self::Confirmed, self::Contract], true);
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
