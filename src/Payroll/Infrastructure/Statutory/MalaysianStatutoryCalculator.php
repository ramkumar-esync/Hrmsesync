<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Statutory;

use HR\Payroll\Domain\Service\StatutoryContext;
use HR\Payroll\Domain\Service\StatutoryContributionCalculator;
use HR\Payroll\Domain\Service\StatutoryResult;
use HR\Payroll\Domain\ValueObject\EmployerContribution;
use HR\Payroll\Domain\ValueObject\PayComponent;
use HR\Payroll\Domain\ValueObject\PayComponentType;
use HR\Shared\Domain\ValueObject\Money;

/**
 * EPF, SOCSO and EIS for Malaysian payroll.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * READ THIS BEFORE PRODUCTION USE
 *
 * Every rate, ceiling, age band and contribution table used here comes from
 * config/statutory/malaysia.php. The values shipped in this repository are
 * starting points for development, not a certified rate set. Statutory rates
 * change — sometimes mid-year, sometimes retroactively.
 *
 * Before running real payroll, have your payroll or tax adviser reconcile
 * config/statutory/malaysia.php against the current published tables from
 * KWSP (EPF), PERKESO (SOCSO and EIS) and LHDN, and re-run the reconciliation
 * whenever the rates are revised. The structure below is designed so that is a
 * config change and nothing more.
 * ─────────────────────────────────────────────────────────────────────────────
 */
final readonly class MalaysianStatutoryCalculator implements StatutoryContributionCalculator
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config, private string $currency) {}

    public function calculate(StatutoryContext $context): StatutoryResult
    {
        $deductions = [];
        $contributions = [];

        if ($context->epfApplicable) {
            [$employeeEpf, $employerEpf] = $this->epf($context);

            if (! $employeeEpf->isZero()) {
                $deductions[] = PayComponent::deduction(
                    PayComponentType::Epf, $employeeEpf, 'EPF (employee)', systemGenerated: true,
                );
            }

            if (! $employerEpf->isZero()) {
                $contributions[] = new EmployerContribution(
                    PayComponentType::Epf, $employerEpf, 'EPF (employer)',
                );
            }
        }

        if ($context->socsoApplicable) {
            $socso = $this->bandedContribution('socso', $context);

            if (! $socso['employee']->isZero()) {
                $deductions[] = PayComponent::deduction(
                    PayComponentType::Socso, $socso['employee'], 'SOCSO (employee)', systemGenerated: true,
                );
            }

            if (! $socso['employer']->isZero()) {
                $contributions[] = new EmployerContribution(
                    PayComponentType::Socso, $socso['employer'], 'SOCSO (employer)',
                );
            }
        }

        if ($context->eisApplicable && $this->eisEligible($context)) {
            $eis = $this->bandedContribution('eis', $context);

            if (! $eis['employee']->isZero()) {
                $deductions[] = PayComponent::deduction(
                    PayComponentType::Eis, $eis['employee'], 'EIS (employee)', systemGenerated: true,
                );
            }

            if (! $eis['employer']->isZero()) {
                $contributions[] = new EmployerContribution(
                    PayComponentType::Eis, $eis['employer'], 'EIS (employer)',
                );
            }
        }

        return new StatutoryResult($deductions, $contributions);
    }

    /** @return array{0: Money, 1: Money} */
    private function epf(StatutoryContext $context): array
    {
        $rates = $this->epfRatesFor($context);

        // EPF contributions are derived from wages rounded up to the next
        // ringgit, mirroring how the statutory Third Schedule is built.
        $wages = $context->statutoryWages->roundedUpToUnit();
        $ceiling = $this->money($this->config['epf']['wage_ceiling'] ?? null);

        if ($ceiling !== null) {
            $wages = $wages->cappedAt($ceiling);
        }

        $employerRate = $wages->isGreaterThan($this->money((string) $this->config['epf']['employer_rate_threshold']))
            ? (float) $rates['employer_above_threshold']
            : (float) $rates['employer_at_or_below_threshold'];

        // Both sides round up to the next ringgit, as the statutory table does.
        return [
            $wages->percentage((float) $rates['employee'])->roundedUpToUnit(),
            $wages->percentage($employerRate)->roundedUpToUnit(),
        ];
    }

    /** @return array<string, string|float> */
    private function epfRatesFor(StatutoryContext $context): array
    {
        $key = match (true) {
            $context->age >= (int) $this->config['epf']['senior_age'] => $context->isCitizen ? 'senior_citizen' : 'senior_non_citizen',
            ! $context->isCitizen => 'non_citizen',
            default => 'standard',
        };

        /** @var array<string, string|float> */
        return $this->config['epf']['rates'][$key] ?? $this->config['epf']['rates']['standard'];
    }

    /** @return array{employee: Money, employer: Money} */
    private function bandedContribution(string $scheme, StatutoryContext $context): array
    {
        $settings = $this->config[$scheme];
        $wages = $context->statutoryWages;

        $ceiling = $this->money($settings['wage_ceiling'] ?? null);
        if ($ceiling !== null) {
            $wages = $wages->cappedAt($ceiling);
        }

        $table = ContributionBandTable::fromConfig($settings['bands'] ?? [], $this->currency);

        if (! $table->isEmpty()) {
            return $table->lookup($wages);
        }

        // Percentage fallback for development. Replace with the published table.
        $seniorOnlyEmployer = $scheme === 'socso'
            && $context->age >= (int) ($settings['employer_only_age'] ?? 60);

        return [
            'employee' => $seniorOnlyEmployer
                ? Money::zero($this->currency)
                : $wages->percentage((float) $settings['employee_rate']),
            'employer' => $wages->percentage((float) (
                $seniorOnlyEmployer ? $settings['employer_rate_senior'] : $settings['employer_rate']
            )),
        ];
    }

    private function eisEligible(StatutoryContext $context): bool
    {
        $maxAge = (int) ($this->config['eis']['max_age'] ?? 60);
        $minAge = (int) ($this->config['eis']['min_age'] ?? 18);

        return $context->age >= $minAge && $context->age < $maxAge;
    }

    private function money(string|int|float|null $amount): ?Money
    {
        return $amount === null ? null : Money::fromDecimal($amount, $this->currency);
    }

    public function describe(): string
    {
        return 'Malaysian statutory engine (EPF/SOCSO/EIS), rate set '
            .($this->config['rate_set_label'] ?? 'unversioned');
    }
}
