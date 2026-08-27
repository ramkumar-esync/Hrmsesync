<?php

declare(strict_types=1);

namespace HR\Payroll\Application\Command;

use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Payroll\Domain\Repository\PayrollRunRepository;
use HR\Payroll\Domain\Repository\PayslipRepository;
use HR\Payroll\Domain\ValueObject\PayrollRunId;

/**
 * Seeds a draft run with a payslip for everyone employed during the period,
 * using their contractual salary. HR then only edits the exceptions.
 */
final readonly class PopulateRunWithActiveStaffHandler
{
    public function __construct(
        private PayrollRunRepository $runs,
        private PayslipRepository $payslips,
        private EmployeeRepository $employees,
        private RecordPayrollEntryHandler $recordEntry,
    ) {}

    /** @return int Number of payslips created. */
    public function __invoke(PopulateRunWithActiveStaff $command): int
    {
        $run = $this->runs->get(PayrollRunId::fromString($command->runId));
        $run->assertAcceptsChanges();

        $created = 0;

        foreach ($this->employees->activeDuring(
            $run->period->startDate()->toDateTimeImmutable(),
            $run->period->endDate()->toDateTimeImmutable(),
        ) as $employee) {
            if ($this->payslips->existsInRunForEmployee($run->id, $employee->id->value)) {
                continue;
            }

            ($this->recordEntry)(new RecordPayrollEntry(
                runId: $run->id->value,
                employeeId: $employee->id->value,
            ));

            $created++;
        }

        return $created;
    }
}
