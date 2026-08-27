<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Statutory;

use HR\Payroll\Domain\Service\IncomeTaxCalculator;
use HR\Payroll\Domain\Service\StatutoryContext;
use HR\Shared\Domain\ValueObject\Money;

/**
 * The default, and the recommended one.
 *
 * Malaysia's MTD formula depends on year-to-date remuneration, accumulated
 * deductions, marital status, dependants, zakat and prior-period corrections —
 * none of which can be inferred from a single month in isolation. Rather than
 * approximate it and quietly under-withhold, this engine returns zero and lets
 * HR enter the figure from their LHDN-approved calculator as a normal PCB line.
 */
final class ManualIncomeTaxCalculator implements IncomeTaxCalculator
{
    public function calculate(StatutoryContext $context, Money $epfRelief): Money
    {
        return Money::zero($context->grossPay->currency);
    }

    public function describe(): string
    {
        return 'PCB entered manually by HR';
    }
}
