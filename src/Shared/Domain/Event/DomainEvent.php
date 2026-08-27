<?php

declare(strict_types=1);

namespace HR\Shared\Domain\Event;

use Carbon\CarbonImmutable;

interface DomainEvent
{
    public function occurredAt(): CarbonImmutable;
}
