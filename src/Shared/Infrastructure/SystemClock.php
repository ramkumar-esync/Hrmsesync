<?php

declare(strict_types=1);

namespace HR\Shared\Infrastructure;

use Carbon\CarbonImmutable;
use HR\Shared\Domain\Contract\Clock;

final class SystemClock implements Clock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfDay();
    }
}
