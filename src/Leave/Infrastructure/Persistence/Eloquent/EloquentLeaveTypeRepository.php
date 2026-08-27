<?php

declare(strict_types=1);

namespace HR\Leave\Infrastructure\Persistence\Eloquent;

use HR\Leave\Domain\Entity\LeaveType;
use HR\Leave\Domain\Exception\LeaveTypeNotFound;
use HR\Leave\Domain\Repository\LeaveTypeRepository;
use HR\Leave\Domain\ValueObject\LeaveTypeId;

final readonly class EloquentLeaveTypeRepository implements LeaveTypeRepository
{
    public function __construct(private LeaveTypeMapper $mapper) {}

    public function save(LeaveType $type): void
    {
        LeaveTypeRecord::query()->updateOrCreate(
            ['id' => $type->id->value],
            $this->mapper->toAttributes($type),
        );
    }

    public function find(LeaveTypeId $id): ?LeaveType
    {
        $record = LeaveTypeRecord::query()->find($id->value);

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function get(LeaveTypeId $id): LeaveType
    {
        return $this->find($id) ?? throw LeaveTypeNotFound::withCode($id->value);
    }

    public function findByCode(string $code): ?LeaveType
    {
        $record = LeaveTypeRecord::query()->where('code', strtoupper($code))->first();

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function allActive(): array
    {
        return LeaveTypeRecord::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (LeaveTypeRecord $record) => $this->mapper->toDomain($record))
            ->all();
    }
}
