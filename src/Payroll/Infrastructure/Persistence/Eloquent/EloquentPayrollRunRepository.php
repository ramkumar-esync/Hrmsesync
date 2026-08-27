<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Persistence\Eloquent;

use HR\Payroll\Domain\Entity\PayrollRun;
use HR\Payroll\Domain\Exception\PayrollRunNotFound;
use HR\Payroll\Domain\Repository\PayrollRunRepository;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Payroll\Domain\ValueObject\PayrollRunId;

final readonly class EloquentPayrollRunRepository implements PayrollRunRepository
{
    public function __construct(private PayrollRunMapper $mapper) {}

    public function save(PayrollRun $run): void
    {
        PayrollRunRecord::query()->updateOrCreate(
            ['id' => $run->id->value],
            $this->mapper->toAttributes($run),
        );
    }

    public function find(PayrollRunId $id): ?PayrollRun
    {
        $record = PayrollRunRecord::query()->find($id->value);

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function get(PayrollRunId $id): PayrollRun
    {
        return $this->find($id) ?? throw PayrollRunNotFound::withId($id);
    }

    public function findByPeriod(PayPeriod $period): ?PayrollRun
    {
        $record = PayrollRunRecord::query()
            ->where('period', (string) $period)
            ->whereNot('status', 'cancelled')
            ->first();

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function existsForPeriod(PayPeriod $period): bool
    {
        return PayrollRunRecord::query()
            ->where('period', (string) $period)
            ->whereNot('status', 'cancelled')
            ->exists();
    }
}
