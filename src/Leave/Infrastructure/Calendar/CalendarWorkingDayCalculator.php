<?php

declare(strict_types=1);

namespace HR\Leave\Infrastructure\Calendar;

use Carbon\CarbonImmutable;
use HR\Leave\Domain\Service\HolidayCalendar;
use HR\Leave\Domain\Service\WorkingDayCalculator;
use HR\Leave\Domain\ValueObject\DayPortion;
use HR\Leave\Domain\ValueObject\LeaveDay;
use HR\Shared\Domain\ValueObject\DateRange;

/**
 * Expands a date range into chargeable days.
 *
 * Rest days and public holidays inside a leave spell are kept in the list but
 * marked non-deductible, so an employee can see exactly why five calendar days
 * only cost them three days of balance.
 */
final readonly class CalendarWorkingDayCalculator implements WorkingDayCalculator
{
    /** @param list<int> $restDays ISO day numbers, 1 = Monday. */
    public function __construct(
        private HolidayCalendar $holidays,
        private array $restDays,
    ) {}

    public function expand(
        DateRange $dates,
        DayPortion $startPortion = DayPortion::Full,
        DayPortion $endPortion = DayPortion::Full,
    ): array {
        $days = [];
        $all = $dates->eachDay();
        $lastIndex = count($all) - 1;

        foreach ($all as $index => $date) {
            $holiday = $this->holidays->holidayOn($date);

            if ($holiday !== null) {
                $days[] = LeaveDay::nonWorking($date, $holiday);

                continue;
            }

            if (in_array($date->dayOfWeekIso, $this->restDays, true)) {
                $days[] = LeaveDay::nonWorking($date, 'Rest day');

                continue;
            }

            $portion = match (true) {
                $index === 0 && $lastIndex === 0 => $startPortion,
                $index === 0 => $startPortion,
                $index === $lastIndex => $endPortion,
                default => DayPortion::Full,
            };

            $days[] = LeaveDay::working($date, $portion);
        }

        return $days;
    }

    public function isWorkingDay(CarbonImmutable $date): bool
    {
        return $this->holidays->holidayOn($date) === null
            && ! in_array($date->dayOfWeekIso, $this->restDays, true);
    }
}
