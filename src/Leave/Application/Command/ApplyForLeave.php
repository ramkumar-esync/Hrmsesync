<?php

declare(strict_types=1);

namespace HR\Leave\Application\Command;

final readonly class ApplyForLeave
{
    public function __construct(
        public string $employeeId,
        public string $leaveTypeId,
        public string $startDate,
        public string $endDate,
        public string $reason,
        public string $startPortion = 'full',
        public string $endPortion = 'full',
        public ?string $attachmentPath = null,
        public ?string $contactWhileAway = null,
    ) {}
}
