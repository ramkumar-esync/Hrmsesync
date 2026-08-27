<?php

declare(strict_types=1);

namespace HR\Employee\Domain\ValueObject;

use HR\Shared\Domain\Exception\InvariantViolation;
use HR\Shared\Domain\ValueObject\Money;

/** What the employee is contracted to be paid, before any run-specific input. */
final readonly class Compensation
{
    public function __construct(
        public Money $basicSalary,
        public PayFrequency $frequency = PayFrequency::Monthly,
        public ?Money $fixedAllowance = null,
    ) {
        if ($basicSalary->isNegative()) {
            throw InvariantViolation::because('Basic salary cannot be negative.');
        }

        if ($fixedAllowance?->isNegative()) {
            throw InvariantViolation::because('A fixed allowance cannot be negative.');
        }
    }

    public function contractualGross(): Money
    {
        return $this->fixedAllowance === null
            ? $this->basicSalary
            : $this->basicSalary->add($this->fixedAllowance);
    }

    /**
     * Daily rate used for unpaid-leave deductions.
     *
     * Uses a fixed working-days divisor rather than the calendar month so that
     * the same absence costs the same in February and March. The divisor is
     * configurable because organisations genuinely differ on this.
     */
    public function dailyRate(int $workingDaysPerMonth): Money
    {
        if ($workingDaysPerMonth < 1) {
            throw InvariantViolation::because('The working-days divisor must be at least 1.');
        }

        return $this->contractualGross()->multipliedBy(1 / $workingDaysPerMonth);
    }
}
