<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Repository;

use HR\Payroll\Domain\Entity\Payslip;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Payroll\Domain\ValueObject\PayslipId;

interface PayslipRepository
{
    public function save(Payslip $payslip): void;

    public function find(PayslipId $id): ?Payslip;

    /** @throws \HR\Payroll\Domain\Exception\PayslipNotFound */
    public function get(PayslipId $id): Payslip;

    /** @return list<Payslip> */
    public function forRun(PayrollRunId $runId): array;

    public function findInRunForEmployee(PayrollRunId $runId, string $employeeId): ?Payslip;

    public function existsInRunForEmployee(PayrollRunId $runId, string $employeeId): bool;

    public function findIssuedForEmployeeAndPeriod(string $employeeId, PayPeriod $period): ?Payslip;

    public function delete(PayslipId $id): void;
}
