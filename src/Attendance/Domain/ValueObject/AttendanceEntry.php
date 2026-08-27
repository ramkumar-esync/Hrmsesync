<?php

declare(strict_types=1);

namespace HR\Attendance\Domain\ValueObject;

use Carbon\CarbonImmutable;

/**
 * One reported day on the sheet: Date, Day, Hours, Leave Type, Remarks.
 *
 * "Day" is not stored — it is the weekday of the date, derived on read, so the
 * two can never disagree. Hours are held as whole minutes (an integer) to keep
 * arithmetic exact, matching how money is handled elsewhere; 7.5 hours is 450.
 *
 * A row may report worked hours, or a leave day (leaveTypeCode set), or both in
 * the case of a half day. leaveTypeCode is the leave type's short code, which is
 * what the sheet reconciles against approved leave when it is submitted.
 */
final readonly class AttendanceEntry
{
    public function __construct(
        public CarbonImmutable $date,
        public int $minutes,
        public ?string $leaveTypeCode,
        public ?string $remarks,
    ) {
        if ($minutes < 0 || $minutes > 24 * 60) {
            throw new \InvalidArgumentException('Hours for a day must be between 0 and 24.');
        }

        if ($minutes === 0 && $leaveTypeCode === null) {
            throw new \InvalidArgumentException(
                'A row must report either hours worked or a leave type.',
            );
        }
    }

    public static function fromHours(
        CarbonImmutable $date,
        float $hours,
        ?string $leaveTypeCode,
        ?string $remarks,
    ): self {
        return new self(
            date: $date->startOfDay(),
            minutes: (int) round($hours * 60),
            leaveTypeCode: $leaveTypeCode !== null && $leaveTypeCode !== '' ? $leaveTypeCode : null,
            remarks: $remarks !== null && trim($remarks) !== '' ? trim($remarks) : null,
        );
    }

    public function hours(): float
    {
        return round($this->minutes / 60, 2);
    }

    /** The weekday name, derived from the date. */
    public function day(): string
    {
        return $this->date->format('l');
    }

    public function isLeave(): bool
    {
        return $this->leaveTypeCode !== null;
    }
}
