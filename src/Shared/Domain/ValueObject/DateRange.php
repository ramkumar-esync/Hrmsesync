<?php

declare(strict_types=1);

namespace HR\Shared\Domain\ValueObject;

use Carbon\CarbonImmutable;
use HR\Shared\Domain\Exception\InvariantViolation;

/** An inclusive range of calendar dates. */
final readonly class DateRange implements \JsonSerializable
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        if ($end->lessThan($start)) {
            throw InvariantViolation::because('The end date cannot fall before the start date.');
        }
    }

    public static function of(string|\DateTimeInterface $start, string|\DateTimeInterface $end): self
    {
        return new self(
            CarbonImmutable::parse($start)->startOfDay(),
            CarbonImmutable::parse($end)->startOfDay(),
        );
    }

    public static function singleDay(string|\DateTimeInterface $date): self
    {
        return self::of($date, $date);
    }

    public function overlaps(self $other): bool
    {
        return $this->start->lessThanOrEqualTo($other->end)
            && $this->end->greaterThanOrEqualTo($other->start);
    }

    public function contains(\DateTimeInterface $date): bool
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        return $date->betweenIncluded($this->start, $this->end);
    }

    public function calendarDays(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    /** @return list<CarbonImmutable> */
    public function eachDay(): array
    {
        $days = [];

        for ($day = $this->start; $day->lessThanOrEqualTo($this->end); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    public function isSingleDay(): bool
    {
        return $this->start->isSameDay($this->end);
    }

    /** @return array{start: string, end: string} */
    public function jsonSerialize(): array
    {
        return [
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
        ];
    }
}
