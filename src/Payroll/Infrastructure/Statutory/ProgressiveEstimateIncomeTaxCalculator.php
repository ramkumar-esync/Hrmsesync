<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Statutory;

use HR\Payroll\Domain\Service\IncomeTaxCalculator;
use HR\Payroll\Domain\Service\StatutoryContext;
use HR\Shared\Domain\ValueObject\Money;

/**
 * An ESTIMATE only. Not an LHDN-approved MTD calculation.
 *
 * It annualises the current month's pay, applies the progressive bands from
 * config, subtracts flat reliefs and divides back down. That is close enough
 * to sanity-check a payroll run and nowhere near close enough to file.
 *
 * Enable it with PCB_ENGINE=progressive_estimate only in environments where
 * someone reconciles the result before payment.
 */
final readonly class ProgressiveEstimateIncomeTaxCalculator implements IncomeTaxCalculator
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config) {}

    public function calculate(StatutoryContext $context, Money $epfRelief): Money
    {
        $currency = $context->grossPay->currency;
        $periodsPerYear = 12;

        $annualIncome = $context->grossPay->multipliedBy($periodsPerYear);

        $relief = Money::fromDecimal((string) $this->config['individual_relief'], $currency)
            ->add($epfRelief->multipliedBy($periodsPerYear)->cappedAt(
                Money::fromDecimal((string) $this->config['epf_relief_cap'], $currency)
            ));

        if ($context->isMarried) {
            $relief = $relief->add(Money::fromDecimal((string) $this->config['spouse_relief'], $currency));
        }

        if ($context->taxDependants > 0) {
            $relief = $relief->add(
                Money::fromDecimal((string) $this->config['child_relief'], $currency)
                    ->multipliedBy($context->taxDependants)
            );
        }

        $chargeable = $annualIncome->subtract($relief);

        if ($chargeable->isNegative() || $chargeable->isZero()) {
            return Money::zero($currency);
        }

        $annualTax = $this->taxOn($chargeable, $currency);

        return $annualTax->multipliedBy(1 / $periodsPerYear);
    }

    private function taxOn(Money $chargeable, string $currency): Money
    {
        $tax = Money::zero($currency);
        $previousCeiling = Money::zero($currency);

        /** @var array<int, array{up_to: string|null, rate: float}> $bands */
        $bands = $this->config['bands'];

        foreach ($bands as $band) {
            $ceiling = $band['up_to'] === null
                ? null
                : Money::fromDecimal((string) $band['up_to'], $currency);

            $upper = $ceiling !== null && $chargeable->isGreaterThan($ceiling) ? $ceiling : $chargeable;
            $slice = $upper->subtract($previousCeiling);

            if ($slice->isNegative() || $slice->isZero()) {
                break;
            }

            $tax = $tax->add($slice->percentage((float) $band['rate']));

            if ($ceiling === null || ! $chargeable->isGreaterThan($ceiling)) {
                break;
            }

            $previousCeiling = $ceiling;
        }

        return $tax;
    }

    public function describe(): string
    {
        return 'PCB progressive ESTIMATE — verify before filing';
    }
}
