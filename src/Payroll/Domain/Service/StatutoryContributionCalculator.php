<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Service;

/**
 * Port for country-specific statutory contributions.
 *
 * The payroll domain never knows what EPF or SOCSO are — it asks this port what
 * must be withheld and what the employer owes. Swapping country, or updating
 * next year's rate tables, touches one adapter and nothing else.
 */
interface StatutoryContributionCalculator
{
    public function calculate(StatutoryContext $context): StatutoryResult;

    /** Shown in the UI so HR knows which rule set produced the numbers. */
    public function describe(): string;
}
