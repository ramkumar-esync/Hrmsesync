<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Service;

use HR\Shared\Domain\ValueObject\Money;

/**
 * Monthly tax withholding (PCB/MTD in Malaysia).
 *
 * Kept separate from the contribution calculator because tax is the part most
 * organisations either enter by hand or delegate to an accredited engine.
 */
interface IncomeTaxCalculator
{
    public function calculate(StatutoryContext $context, Money $epfRelief): Money;

    public function describe(): string;
}
