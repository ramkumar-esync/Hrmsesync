<?php

declare(strict_types=1);

namespace HR\Payroll\Application\Command;

use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Payroll\Domain\Entity\Payslip;
use HR\Payroll\Domain\Repository\PayrollRunRepository;
use HR\Payroll\Domain\Repository\PayslipRepository;
use HR\Payroll\Domain\Service\PayslipCalculator;
use HR\Payroll\Domain\ValueObject\EmployeeSnapshot;
use HR\Payroll\Domain\ValueObject\PayComponent;
use HR\Payroll\Domain\ValueObject\PayComponentType;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Shared\Application\TransactionManager;
use HR\Shared\Domain\Exception\InvariantViolation;
use HR\Shared\Domain\ValueObject\Money;

/**
 * Creates or replaces the draft payslip for one employee in a run, then
 * recalculates it. Idempotent: sending the same entry twice overwrites rather
 * than duplicating, which is what HR expects when they fix a typo.
 */
final readonly class RecordPayrollEntryHandler
{
    public function __construct(
        private PayrollRunRepository $runs,
        private PayslipRepository $payslips,
        private EmployeeRepository $employees,
        private PayslipCalculator $calculator,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(RecordPayrollEntry $command): Payslip
    {
        return $this->transaction->transactional(function () use ($command): Payslip {
            $run = $this->runs->get(PayrollRunId::fromString($command->runId));
            $run->assertAcceptsChanges();

            $employee = $this->employees->get(EmployeeId::fromString($command->employeeId));

            if (! $employee->wasEmployedDuring($run->period->startDate(), $run->period->endDate())) {
                throw InvariantViolation::because(
                    "{$employee->name()->full} was not employed during {$run->period->label()}."
                );
            }

            $existing = $this->payslips->findInRunForEmployee($run->id, $employee->id->value);

            $payslip = $existing ?? Payslip::draft(
                runId: $run->id,
                period: $run->period,
                employee: EmployeeSnapshot::fromEmployee($employee),
            );

            $payslip->replaceEarnings($this->buildEarnings($command, $employee));
            $payslip->clearCalculatedLines();

            foreach ($command->deductions as $line) {
                $payslip->addDeduction(PayComponent::deduction(
                    type: PayComponentType::from($line['type']),
                    amount: Money::fromDecimal($line['amount']),
                    description: $line['description'] ?? null,
                ));
            }

            $this->calculator->recalculate($payslip, $employee);
            $payslip->addRemarks($command->remarks);

            $this->payslips->save($payslip);

            return $payslip;
        });
    }

    /** @return list<PayComponent> */
    private function buildEarnings(RecordPayrollEntry $command, \HR\Employee\Domain\Entity\Employee $employee): array
    {
        $earnings = [];

        if ($command->useContractualSalary) {
            $compensation = $employee->compensation();

            $earnings[] = PayComponent::earning(
                PayComponentType::BasicSalary,
                $compensation->basicSalary,
                'Basic salary',
            );

            if ($compensation->fixedAllowance !== null && ! $compensation->fixedAllowance->isZero()) {
                $earnings[] = PayComponent::earning(
                    PayComponentType::FixedAllowance,
                    $compensation->fixedAllowance,
                    'Fixed allowance',
                );
            }
        }

        foreach ($command->earnings as $line) {
            $earnings[] = PayComponent::earning(
                type: PayComponentType::from($line['type']),
                amount: Money::fromDecimal($line['amount']),
                description: $line['description'] ?? null,
            );
        }

        if ($earnings === []) {
            throw InvariantViolation::because('A payslip needs at least one earning line.');
        }

        return $earnings;
    }
}
