<?php

declare(strict_types=1);

namespace HR\Leave\Application\Command;

final readonly class DecideLeaveApplication
{
    public function __construct(
        public string $applicationId,
        public string $approverEmployeeId,
        public bool $approve,
        public ?string $note = null,
    ) {}
}
