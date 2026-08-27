<?php

declare(strict_types=1);

namespace HR\Attendance\Application\Command;

/**
 * HR's decision on a submitted sheet. approve = true accepts it; false returns
 * it for changes, which requires a note explaining what to fix.
 */
final readonly class DecideAttendanceSheet
{
    public function __construct(
        public string $sheetId,
        public string $approverEmployeeId,
        public bool $approve,
        public ?string $note = null,
    ) {}
}
