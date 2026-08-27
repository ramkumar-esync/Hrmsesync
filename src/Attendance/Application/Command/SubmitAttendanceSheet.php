<?php

declare(strict_types=1);

namespace HR\Attendance\Application\Command;

final readonly class SubmitAttendanceSheet
{
    public function __construct(
        public string $employeeId,
        public string $period,
    ) {}
}
