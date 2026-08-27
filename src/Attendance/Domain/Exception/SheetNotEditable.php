<?php

declare(strict_types=1);

namespace HR\Attendance\Domain\Exception;

use HR\Shared\Domain\Exception\DomainException;

final class SheetNotEditable extends DomainException
{
    public function statusCode(): int
    {
        return 409;
    }

    public static function because(string $why): self
    {
        return new self($why);
    }
}
