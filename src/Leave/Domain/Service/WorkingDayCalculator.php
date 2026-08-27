<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Service;

use Carbon\CarbonImmutable;
use HR\Leave\Domain\ValueObject\DayPortion;
use HR\Leave\Domain\ValueObject\LeaveDay;
use HR\Shared\Domain\ValueObject\DateRange;

/**
 * Turns "8th to 12th, starting after lunch" into the exact days that count.
 *
 * Every organisation answers this differently — six-day weeks, state-specific
 * public holidays, shift rosters — so it is a port with one supplied adapter.
 */
interface WorkingDayCalculator
{
    /** @return list<LeaveDay> */
    public function expand(
        DateRange $dates,
        DayPortion $startPortion = DayPortion::Full,
        DayPortion $endPortion = DayPortion::Full,
    ): array;

    public function isWorkingDay(CarbonImmutable $date): bool;
}
