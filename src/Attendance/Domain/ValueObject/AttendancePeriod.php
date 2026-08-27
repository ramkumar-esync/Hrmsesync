<?php

declare(strict_types=1);

namespace HR\Attendance\Domain\ValueObject;

use Carbon\CarbonImmutable;

/**
 * A calendar month, e.g. "2026-07". The unit an attendance sheet covers.
 */
final readonly class AttendancePeriod
{
    public function __construct(public int $year, public int $month)
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException("Month must be 1–12, got {$month}.");
        }

        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException("Year looks wrong: {$year}.");
        }
    }

    public static function fromString(string $value): self
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $value, $m)) {
            throw new \InvalidArgumentException("Period must be YYYY-MM, got \"{$value}\".");
        }

        return new self((int) $m[1], (int) $m[2]);
    }

    public function contains(CarbonImmutable $date): bool
    {
        return $date->year === $this->year && $date->month === $this->month;
    }

    public function firstDay(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, $this->month, 1)->startOfDay();
    }

    public function lastDay(): CarbonImmutable
    {
        return $this->firstDay()->endOfMonth()->startOfDay();
    }

    public function toString(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function label(): string
    {
        return $this->firstDay()->format('F Y');
    }
}
