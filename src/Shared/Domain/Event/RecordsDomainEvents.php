<?php

declare(strict_types=1);

namespace HR\Shared\Domain\Event;

/**
 * Aggregates record what happened; the application layer decides when to
 * publish. Nothing in the domain layer touches the framework's event bus.
 */
trait RecordsDomainEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /** @return list<DomainEvent> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
