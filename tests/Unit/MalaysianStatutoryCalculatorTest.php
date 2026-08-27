<?php

declare(strict_types=1);

namespace Tests\Unit;

use HR\Payroll\Domain\Service\StatutoryContext;
use HR\Payroll\Domain\ValueObject\PayComponentType;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Payroll\Infrastructure\Statutory\MalaysianStatutoryCalculator;
use HR\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * These assert the engine's *behaviour* against a fixed test rate set, not the
 * correctness of any particular published rate. Reconciling the shipped config
 * against the official tables is a separate, human job — see the class docblock
 * on MalaysianStatutoryCalculator.
 */
final class MalaysianStatutoryCalculatorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function rateSet(): array
    {
        return [
            'rate_set_label' => 'TEST-FIXTURE',
            'epf' => [
                'senior_age' => 60,
                'employer_rate_threshold' => '5000.00',
                'wage_ceiling' => null,
                'rates' => [
                    'standard' => [
                        'employee' => 11.0,
                        'employer_at_or_below_threshold' => 13.0,
                        'employer_above_threshold' => 12.0,
                    ],
                    'senior_citizen' => [
                        'employee' => 0.0,
                        'employer_at_or_below_threshold' => 4.0,
                        'employer_above_threshold' => 4.0,
                    ],
                ],
            ],
            'socso' => [
                'wage_ceiling' => '6000.00',
                'employer_only_age' => 60,
                'employee_rate' => 0.5,
                'employer_rate' => 1.75,
                'employer_rate_senior' => 1.25,
                'bands' => [],
            ],
            'eis' => [
                'wage_ceiling' => '6000.00',
                'min_age' => 18,
                'max_age' => 60,
                'employee_rate' => 0.2,
                'employer_rate' => 0.2,
                'bands' => [],
            ],
        ];
    }

    private function calculator(): MalaysianStatutoryCalculator
    {
        return new MalaysianStatutoryCalculator($this->rateSet(), 'MYR');
    }

    private function context(string $wages, int $age = 30): StatutoryContext
    {
        return new StatutoryContext(
            statutoryWages: Money::fromDecimal($wages, 'MYR'),
            grossPay: Money::fromDecimal($wages, 'MYR'),
            age: $age,
            isCitizen: true,
            epfApplicable: true,
            socsoApplicable: true,
            eisApplicable: true,
            period: PayPeriod::fromString('2026-07'),
        );
    }

    private function deduction($result, PayComponentType $type): ?string
    {
        foreach ($result->employeeDeductions as $line) {
            if ($line->type === $type) {
                return $line->amount->toDecimal();
            }
        }

        return null;
    }

    private function contribution($result, PayComponentType $type): ?string
    {
        foreach ($result->employerContributions as $line) {
            if ($line->type === $type) {
                return $line->amount->toDecimal();
            }
        }

        return null;
    }

    public function test_employer_rate_steps_down_above_the_threshold(): void
    {
        $lower = $this->calculator()->calculate($this->context('5000.00'));
        $higher = $this->calculator()->calculate($this->context('6000.00'));

        // 13% of 5,000 = 650; 12% of 6,000 = 720.
        $this->assertSame('650.00', $this->contribution($lower, PayComponentType::Epf));
        $this->assertSame('720.00', $this->contribution($higher, PayComponentType::Epf));
    }

    public function test_epf_amounts_round_up_to_the_next_whole_unit(): void
    {
        // 11% of 3,456 = 380.16 → rounded up to 381.
        $result = $this->calculator()->calculate($this->context('3456.00'));

        $this->assertSame('381.00', $this->deduction($result, PayComponentType::Epf));
    }

    public function test_socso_and_eis_respect_the_wage_ceiling(): void
    {
        $result = $this->calculator()->calculate($this->context('12000.00'));

        // Capped at 6,000: SOCSO employee 0.5% = 30.00, EIS 0.2% = 12.00.
        $this->assertSame('30.00', $this->deduction($result, PayComponentType::Socso));
        $this->assertSame('12.00', $this->deduction($result, PayComponentType::Eis));
    }

    public function test_senior_employees_stop_contributing_to_eis(): void
    {
        $result = $this->calculator()->calculate($this->context('5000.00', age: 62));

        $this->assertNull($this->deduction($result, PayComponentType::Eis));
        $this->assertNull($this->contribution($result, PayComponentType::Eis));
    }

    public function test_senior_employees_pay_no_epf_but_the_employer_still_does(): void
    {
        $result = $this->calculator()->calculate($this->context('5000.00', age: 62));

        $this->assertNull($this->deduction($result, PayComponentType::Epf));
        $this->assertSame('200.00', $this->contribution($result, PayComponentType::Epf));
    }

    public function test_an_employee_exempt_from_a_scheme_is_not_charged_for_it(): void
    {
        $context = new StatutoryContext(
            statutoryWages: Money::fromDecimal('5000.00', 'MYR'),
            grossPay: Money::fromDecimal('5000.00', 'MYR'),
            age: 30,
            isCitizen: false,
            epfApplicable: false,
            socsoApplicable: true,
            eisApplicable: false,
            period: PayPeriod::fromString('2026-07'),
        );

        $result = $this->calculator()->calculate($context);

        $this->assertNull($this->deduction($result, PayComponentType::Epf));
        $this->assertNull($this->deduction($result, PayComponentType::Eis));
        $this->assertSame('25.00', $this->deduction($result, PayComponentType::Socso));
    }

    public function test_a_published_band_table_takes_precedence_over_percentages(): void
    {
        $rates = $this->rateSet();
        $rates['socso']['bands'] = [
            ['up_to' => '3000.00', 'employee' => '14.75', 'employer' => '51.65'],
            ['up_to' => null, 'employee' => '19.75', 'employer' => '69.05'],
        ];

        $calculator = new MalaysianStatutoryCalculator($rates, 'MYR');
        $result = $calculator->calculate($this->context('4000.00'));

        $this->assertSame('19.75', $this->deduction($result, PayComponentType::Socso));
        $this->assertSame('69.05', $this->contribution($result, PayComponentType::Socso));
    }
}
