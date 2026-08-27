<?php

declare(strict_types=1);

namespace HR\Attendance\Application\Command;

/**
 * The employee saves the rows for a month. entries is the full set for the
 * period — the sheet replaces what it holds with these.
 *
 * @param list<array{date:string,hours:float|int|string,leave_type_code:?string,remarks:?string}> $entries
 */
final readonly class SaveAttendanceSheet
{
    public function __construct(
        public string $employeeId,
        public string $period,
        public array $entries,
    ) {}
}
