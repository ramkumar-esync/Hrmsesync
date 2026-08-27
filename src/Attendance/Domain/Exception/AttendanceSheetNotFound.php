<?php

declare(strict_types=1);

namespace HR\Attendance\Domain\Exception;

use HR\Shared\Domain\Exception\EntityNotFound;

final class AttendanceSheetNotFound extends EntityNotFound
{
    public static function forPeriod(string $employeeId, string $period): self
    {
        return new self("No attendance sheet for {$period}.");
    }

    public static function withId(string $id): self
    {
        return new self('That attendance sheet no longer exists.');
    }
}
