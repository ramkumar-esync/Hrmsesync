<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use HR\Payroll\Domain\Entity\Payslip;
use HR\Payroll\Domain\Event\PayslipIssued;
use HR\Payroll\Domain\Exception\PayslipLocked;
use HR\Payroll\Domain\ValueObject\EmployeeSnapshot;
use HR\Payroll\Domain\ValueObject\EmployerContribution;
use HR\Payroll\Domain\ValueObject\PayComponent;
use HR\Payroll\Domain\ValueObject\PayComponentType;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Shared\Domain\Exception\InvariantViolation;
use HR\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PayslipTest extends TestCase
{
    private function snapshot(): EmployeeSnapshot
    {
        return new EmployeeSnapshot(
            employeeId: '018f0000-0000-7000-8000-000000000001',
            employeeNumber: 'EMP-0001',
            name: 'Test Person',
            jobTitle: 'Engineer',
            department: 'Engineering',
            epfNumber: null,
            socsoNumber: null,
            taxReferenceNumber: null,
            bankName: null,
            bankAccountMasked: null,
        );
    }

    private function payslip(): Payslip
    {
        return Payslip::draft(
            runId: PayrollRunId::generate(),
            period: PayPeriod::fromString('2026-07'),
            employee: $this->snapshot(),
            earnings: [
                PayComponent::earning(PayComponentType::BasicSalary, Money::fromDecimal('5000.00')),
                PayComponent::earning(PayComponentType::FixedAllowance, Money::fromDecimal('500.00')),
            ],
        );
    }

    public function test_net_pay_is_gross_less_deductions(): void
    {
        $payslip = $this->payslip();
        $payslip->addDeduction(PayComponent::deduction(
            PayComponentType::Epf, Money::fromDecimal('605.00'), systemGenerated: true,
        ));

        $this->assertSame('5500.00', $payslip->grossPay()->toDecimal());
        $this->assertSame('605.00', $payslip->totalDeductions()->toDecimal());
        $this->assertSame('4895.00', $payslip->netPay()->toDecimal());
    }

    public function test_employer_contributions_do_not_change_net_pay(): void
    {
        $payslip = $this->payslip();
        $payslip->addEmployerContribution(new EmployerContribution(
            PayComponentType::Epf, Money::fromDecimal('715.00'),
        ));

        $this->assertSame('5500.00', $payslip->netPay()->toDecimal());
        $this->assertSame('6215.00', $payslip->employerCost()->toDecimal());
    }

    public function test_reimbursed_claims_are_paid_but_excluded_from_statutory_wages(): void
    {
        $payslip = $this->payslip();
        $payslip->addEarning(PayComponent::earning(
            PayComponentType::Claim, Money::fromDecimal('300.00'), 'Travel claim',
        ));

        $this->assertSame('5800.00', $payslip->grossPay()->toDecimal());
        $this->assertSame('5500.00', $payslip->statutoryWages()->toDecimal());
    }

    public function test_clearing_calculated_lines_keeps_lines_entered_by_hand(): void
    {
        $payslip = $this->payslip();
        $payslip->addDeduction(PayComponent::deduction(
            PayComponentType::Epf, Money::fromDecimal('605.00'), systemGenerated: true,
        ));
        $payslip->addDeduction(PayComponent::deduction(
            PayComponentType::Advance, Money::fromDecimal('200.00'), 'Salary advance',
        ));

        $payslip->clearCalculatedLines();

        $this->assertCount(1, $payslip->deductions());
        $this->assertSame(PayComponentType::Advance, $payslip->deductions()[0]->type);
    }

    public function test_issuing_records_an_event(): void
    {
        $payslip = $this->payslip();
        $payslip->issue(CarbonImmutable::parse('2026-07-25 09:00:00'));

        $events = $payslip->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(PayslipIssued::class, $events[0]);
        $this->assertSame('5500.00', $events[0]->netPay->toDecimal());
    }

    public function test_an_issued_payslip_cannot_be_edited(): void
    {
        $payslip = $this->payslip();
        $payslip->issue(CarbonImmutable::now());

        $this->expectException(PayslipLocked::class);

        $payslip->addDeduction(PayComponent::deduction(
            PayComponentType::Advance, Money::fromDecimal('50.00'),
        ));
    }

    public function test_it_refuses_to_issue_a_payslip_with_negative_net_pay(): void
    {
        $payslip = $this->payslip();
        $payslip->addDeduction(PayComponent::deduction(
            PayComponentType::Advance, Money::fromDecimal('6000.00'), 'Overlarge advance',
        ));

        $this->expectException(InvariantViolation::class);

        $payslip->issue(CarbonImmutable::now());
    }

    public function test_a_deduction_cannot_be_recorded_as_an_earning(): void
    {
        $this->expectException(InvariantViolation::class);

        PayComponent::earning(PayComponentType::Epf, Money::fromDecimal('100.00'));
    }

    public function test_a_component_cannot_be_negative(): void
    {
        $this->expectException(InvariantViolation::class);

        new PayComponent(PayComponentType::Bonus, Money::fromDecimal('-100.00'));
    }
}
