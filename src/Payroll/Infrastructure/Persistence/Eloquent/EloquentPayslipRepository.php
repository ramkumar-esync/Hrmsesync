<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Persistence\Eloquent;

use HR\Payroll\Domain\Entity\Payslip;
use HR\Payroll\Domain\Exception\PayslipNotFound;
use HR\Payroll\Domain\Repository\PayslipRepository;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Payroll\Domain\ValueObject\PayslipId;

final readonly class EloquentPayslipRepository implements PayslipRepository
{
    public function __construct(private PayslipMapper $mapper) {}

    public function save(Payslip $payslip): void
    {
        PayslipRecord::query()->updateOrCreate(
            ['id' => $payslip->id->value],
            $this->mapper->toAttributes($payslip),
        );
    }

    public function find(PayslipId $id): ?Payslip
    {
        $record = PayslipRecord::query()->find($id->value);

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function get(PayslipId $id): Payslip
    {
        return $this->find($id) ?? throw PayslipNotFound::withId($id);
    }

    public function forRun(PayrollRunId $runId): array
    {
        return PayslipRecord::query()
            ->where('payroll_run_id', $runId->value)
            ->orderBy('id')
            ->get()
            ->map(fn (PayslipRecord $record) => $this->mapper->toDomain($record))
            ->all();
    }

    public function findInRunForEmployee(PayrollRunId $runId, string $employeeId): ?Payslip
    {
        $record = PayslipRecord::query()
            ->where('payroll_run_id', $runId->value)
            ->where('employee_id', $employeeId)
            ->first();

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function existsInRunForEmployee(PayrollRunId $runId, string $employeeId): bool
    {
        return PayslipRecord::query()
            ->where('payroll_run_id', $runId->value)
            ->where('employee_id', $employeeId)
            ->exists();
    }

    public function findIssuedForEmployeeAndPeriod(string $employeeId, PayPeriod $period): ?Payslip
    {
        $record = PayslipRecord::query()
            ->where('employee_id', $employeeId)
            ->where('period', (string) $period)
            ->where('status', 'issued')
            ->first();

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function delete(PayslipId $id): void
    {
        PayslipRecord::query()->whereKey($id->value)->delete();
    }
}
