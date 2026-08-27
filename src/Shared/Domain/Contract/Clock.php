<?php

declare(strict_types=1);

namespace HR\Shared\Domain\Contract;

use Carbon\CarbonImmutable;

/** Injected so date-sensitive rules (backdating, accruals) stay testable. */
interface Clock
{
    public function now(): CarbonImmutable;

    public function today(): CarbonImmutable;
}
