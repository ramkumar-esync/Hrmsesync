<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\ValueObject;

use Carbon\CarbonImmutable;
use HR\Shared\Domain\Exception\InvariantViolation;
use HR\Shared\Domain\ValueObject\DateRange;

/** A calendar month of payroll, identified as "2026-07". */
final readonly class PayPeriod implements \JsonSerializable, \Stringable
{
    public function __construct(public int $year, public int $month)
    {
        if ($year < 2000 || $year > 2100) {
            throw InvariantViolation::because("Pay period year {$year} is out of range.");
        }

        if ($month < 1 || $month > 12) {
            throw InvariantViolation::because("Pay period month {$month} is out of range.");
        }
    }

    public static function fromString(string $value): self
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $value, $matches)) {
            throw InvariantViolation::because("\"{$value}\" is not a pay period. Use YYYY-MM.");
        }

        return new self((int) $matches[1], (int) $matches[2]);
    }

    public static function of(\DateTimeInterface $date): self
    {
        $date = CarbonImmutable::parse($date);

        return new self($date->year, $date->month);
    }

    public function startDate(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, $this->month, 1)->startOfDay();
    }

    public function endDate(): CarbonImmutable
    {
        return $this->startDate()->endOfMonth()->startOfDay();
    }

    public function range(): DateRange
    {
        return new DateRange($this->startDate(), $this->endDate());
    }

    public function next(): self
    {
        $next = $this->startDate()->addMonth();

        return new self($next->year, $next->month);
    }

    public function previous(): self
    {
        $previous = $this->startDate()->subMonth();

        return new self($previous->year, $previous->month);
    }

    public function label(): string
    {
        return $this->startDate()->format('F Y');
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->month === $other->month;
    }

    public function jsonSerialize(): string
    {
        return $this->__toString();
    }

    public function __toString(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }
}
