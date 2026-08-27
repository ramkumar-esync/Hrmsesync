<?php

declare(strict_types=1);

namespace HR\Leave\Infrastructure\Calendar;

use Carbon\CarbonImmutable;
use HR\Leave\Domain\Service\HolidayCalendar;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\DatabaseManager;

/**
 * Public holidays from the database, cached per year.
 *
 * Malaysian public holidays vary by state and several move each year, so they
 * are data that HR maintains rather than a hard-coded list.
 */
final readonly class DatabaseHolidayCalendar implements HolidayCalendar
{
    public function __construct(
        private DatabaseManager $database,
        private Cache $cache,
        private ?string $region = null,
    ) {}

    public function holidayOn(CarbonImmutable $date): ?string
    {
        return $this->holidaysFor($date->year)[$date->toDateString()] ?? null;
    }

    /** @return array<string, string> */
    private function holidaysFor(int $year): array
    {
        return $this->cache->remember(
            "holidays:{$year}:".($this->region ?? 'national'),
            now()->addDay(),
            fn () => $this->database->table('public_holidays')
                ->whereYear('date', $year)
                ->where(function ($query): void {
                    $query->whereNull('region');

                    if ($this->region !== null) {
                        $query->orWhere('region', $this->region);
                    }
                })
                ->pluck('name', 'date')
                ->map(static fn ($name) => (string) $name)
                ->all(),
        );
    }
}
