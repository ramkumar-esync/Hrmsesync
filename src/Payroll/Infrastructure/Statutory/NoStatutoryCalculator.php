<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Statutory;

use HR\Payroll\Domain\Service\StatutoryContext;
use HR\Payroll\Domain\Service\StatutoryContributionCalculator;
use HR\Payroll\Domain\Service\StatutoryResult;

/**
 * For jurisdictions not yet implemented, or for demos. Every deduction is then
 * entered by hand, which is transparent if slow.
 */
final class NoStatutoryCalculator implements StatutoryContributionCalculator
{
    public function calculate(StatutoryContext $context): StatutoryResult
    {
        return StatutoryResult::none();
    }

    public function describe(): string
    {
        return 'No statutory engine — all deductions entered manually';
    }
}
