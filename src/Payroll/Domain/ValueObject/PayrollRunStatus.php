<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\ValueObject;

enum PayrollRunStatus: string
{
    /** Being built. Entries can be added, edited and removed. */
    case Draft = 'draft';

    /** Locked and payslips issued. Nothing can be edited after this point. */
    case Finalised = 'finalised';

    /** Bank transfer confirmed. */
    case Paid = 'paid';

    /** Abandoned before finalisation. */
    case Cancelled = 'cancelled';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
