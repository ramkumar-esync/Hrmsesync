<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Service;

use Carbon\CarbonImmutable;

interface HolidayCalendar
{
    public function holidayOn(CarbonImmutable $date): ?string;
}
