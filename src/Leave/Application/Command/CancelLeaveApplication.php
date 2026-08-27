<?php

declare(strict_types=1);

namespace HR\Leave\Application\Command;

final readonly class CancelLeaveApplication
{
    public function __construct(
        public string $applicationId,
        public string $cancelledBy,
        public bool $allowAfterStart = false,
    ) {}
}
