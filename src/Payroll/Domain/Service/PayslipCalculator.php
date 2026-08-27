<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Service;

use HR\Employee\Domain\Entity\Employee;
use HR\Payroll\Domain\Entity\Payslip;
use HR\Payroll\Domain\ValueObject\PayComponent;
use HR\Payroll\Domain\ValueObject\PayComponentType;
use HR\Shared\Domain\ValueObject\Money;

/**
 * The one place where a payslip's numbers are decided.
 *
 * Order matters and is fixed here on purpose:
 *   1. earnings entered by HR
 *   2. unpaid-leave deduction, derived from the Leave context
 *   3. statutory contributions on the resulting wage base
 *   4. income tax, which needs the EPF figure from step 3
 */
final readonly class PayslipCalculator
{
    public function __construct(
        private StatutoryContributionCalculator $statutory,
        private IncomeTaxCalculator $tax,
        private UnpaidLeaveLedger $unpaidLeave,
        private int $workingDaysPerMonth,
    ) {}

    public function recalculate(Payslip $payslip, Employee $employee): void
    {
        $payslip->clearCalculatedLines();

        $this->applyUnpaidLeave($payslip, $employee);

        $context = StatutoryContext::for(
            employee: $employee,
            statutoryWages: $payslip->statutoryWages()->subtract($this->unpaidLeaveAmount($payslip)),
            grossPay: $payslip->grossPay(),
            period: $payslip->period,
        );

        $result = $this->statutory->calculate($context);

        foreach ($result->employeeDeductions as $deduction) {
            $payslip->addDeduction($deduction);
        }

        foreach ($result->employerContributions as $contribution) {
            $payslip->addEmployerContribution($contribution);
        }

        $epfRelief = $payslip->deductionOfType(PayComponentType::Epf)?->amount
            ?? Money::zero($payslip->currency());

        $tax = $this->tax->calculate($context, $epfRelief);

        if (! $tax->isZero()) {
            $payslip->addDeduction(PayComponent::deduction(
                type: PayComponentType::Pcb,
                amount: $tax,
                description: 'Monthly tax deduction (PCB)',
                systemGenerated: true,
            ));
        }
    }

    private function applyUnpaidLeave(Payslip $payslip, Employee $employee): void
    {
        $days = $this->unpaidLeave->unpaidDaysFor($employee->id->value, $payslip->period);

        if ($days <= 0.0) {
            return;
        }

        $amount = $employee->compensation()
            ->dailyRate($this->workingDaysPerMonth)
            ->multipliedBy($days);

        $payslip->addDeduction(PayComponent::deduction(
            type: PayComponentType::UnpaidLeave,
            amount: $amount,
            description: sprintf('Unpaid leave (%s %s)', rtrim(rtrim(number_format($days, 1), '0'), '.'), $days == 1.0 ? 'day' : 'days'),
            systemGenerated: true,
        ));
    }

    private function unpaidLeaveAmount(Payslip $payslip): Money
    {
        return $payslip->deductionOfType(PayComponentType::UnpaidLeave)?->amount
            ?? Money::zero($payslip->currency());
    }

    public function engineDescription(): string
    {
        return $this->statutory->describe().' · '.$this->tax->describe();
    }
}
