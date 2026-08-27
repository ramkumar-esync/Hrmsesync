<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Service;

use HR\Employee\Domain\Entity\Employee;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Shared\Domain\ValueObject\Money;

/** Everything a statutory engine needs, and nothing it does not. */
final readonly class StatutoryContext
{
    public function __construct(
        public Money $statutoryWages,
        public Money $grossPay,
        public int $age,
        public bool $isCitizen,
        public bool $epfApplicable,
        public bool $socsoApplicable,
        public bool $eisApplicable,
        public PayPeriod $period,
        public int $taxDependants = 0,
        public bool $isMarried = false,
    ) {}

    public static function for(Employee $employee, Money $statutoryWages, Money $grossPay, PayPeriod $period): self
    {
        $profile = $employee->statutoryProfile();

        return new self(
            statutoryWages: $statutoryWages,
            grossPay: $grossPay,
            age: $profile->ageOn($period->endDate()),
            isCitizen: $profile->isCitizen,
            epfApplicable: $profile->epfApplicable,
            socsoApplicable: $profile->socsoApplicable,
            eisApplicable: $profile->eisApplicable,
            period: $period,
            taxDependants: $profile->taxDependants,
            isMarried: $profile->isMarried,
        );
    }
}
