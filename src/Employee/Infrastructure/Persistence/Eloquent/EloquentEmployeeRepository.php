<?php

declare(strict_types=1);

namespace HR\Employee\Infrastructure\Persistence\Eloquent;

use HR\Employee\Domain\Entity\Employee;
use HR\Employee\Domain\Exception\EmployeeNotFound;
use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Employee\Domain\ValueObject\EmployeeNumber;

final readonly class EloquentEmployeeRepository implements EmployeeRepository
{
    public function __construct(private EmployeeMapper $mapper) {}

    public function save(Employee $employee): void
    {
        EmployeeRecord::query()->updateOrCreate(
            ['id' => $employee->id->value],
            $this->mapper->toAttributes($employee),
        );
    }

    public function find(EmployeeId $id): ?Employee
    {
        $record = EmployeeRecord::query()->find($id->value);

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function get(EmployeeId $id): Employee
    {
        return $this->find($id) ?? throw EmployeeNotFound::withId($id);
    }

    public function findByEmployeeNumber(EmployeeNumber $number): ?Employee
    {
        $record = EmployeeRecord::query()->where('employee_number', $number->value)->first();

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function findByUserId(string $userId): ?Employee
    {
        $record = EmployeeRecord::query()->where('user_id', $userId)->first();

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function employeeNumberExists(EmployeeNumber $number): bool
    {
        return EmployeeRecord::query()->where('employee_number', $number->value)->exists();
    }

    public function activeDuring(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return EmployeeRecord::query()
            ->whereDate('joined_on', '<=', $end)
            ->where(function ($query) use ($start): void {
                $query->whereNull('left_on')->orWhereDate('left_on', '>=', $start);
            })
            ->orderBy('employee_number')
            ->get()
            ->map(fn (EmployeeRecord $record) => $this->mapper->toDomain($record))
            ->all();
    }

    public function directReportsOf(EmployeeId $managerId): array
    {
        return EmployeeRecord::query()
            ->where('reports_to', $managerId->value)
            ->pluck('id')
            ->map(static fn (string $id) => EmployeeId::fromString($id))
            ->all();
    }
}
