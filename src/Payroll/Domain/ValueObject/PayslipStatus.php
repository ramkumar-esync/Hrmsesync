<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\ValueObject;

enum PayslipStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Superseded = 'superseded';

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isVisibleToEmployee(): bool
    {
        return $this === self::Issued;
    }
}
