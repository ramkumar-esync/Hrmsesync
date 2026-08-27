<?php

declare(strict_types=1);

namespace HR\Shared\Infrastructure;

use HR\Shared\Application\EventPublisher;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelEventPublisher implements EventPublisher
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function publishAll(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
