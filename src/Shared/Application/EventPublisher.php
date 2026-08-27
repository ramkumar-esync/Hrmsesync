<?php

declare(strict_types=1);

namespace HR\Shared\Application;

use HR\Shared\Domain\Event\DomainEvent;

interface EventPublisher
{
    /** @param list<DomainEvent> $events */
    public function publishAll(array $events): void;
}
