<?php

declare(strict_types=1);

namespace HR\Leave\Domain\ValueObject;

use Carbon\CarbonImmutable;

/**
 * One calendar day of an application, resolved at the time of applying.
 *
 * Storing the expanded days rather than just a start and end date means payroll
 * can ask "how many unpaid days fell in July" and get an exact answer without
 * re-deriving weekends and holidays months later, possibly against a calendar
 * that has since changed.
 */
final readonly class LeaveDay implements \JsonSerializable
{
    public function __construct(
        public CarbonImmutable $date,
        public DayPortion $portion,
        public bool $deductible,
        public ?string $nonWorkingReason = null,
    ) {}

    public static function working(CarbonImmutable $date, DayPortion $portion = DayPortion::Full): self
    {
        return new self($date->startOfDay(), $portion, true);
    }

    public static function nonWorking(CarbonImmutable $date, string $reason): self
    {
        return new self($date->startOfDay(), DayPortion::Full, false, $reason);
    }

    public function days(): float
    {
        return $this->deductible ? $this->portion->days() : 0.0;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'portion' => $this->portion->value,
            'deductible' => $this->deductible,
            'non_working_reason' => $this->nonWorkingReason,
        ];
    }
}
