<?php

declare(strict_types=1);

namespace HR\Leave\Infrastructure\Persistence\Eloquent;

use HR\Leave\Domain\Entity\LeaveEntitlement;
use HR\Leave\Domain\Repository\LeaveEntitlementRepository;
use HR\Leave\Domain\ValueObject\LeaveEntitlementId;
use HR\Leave\Domain\ValueObject\LeaveTypeId;

final readonly class EloquentLeaveEntitlementRepository implements LeaveEntitlementRepository
{
    public function __construct(private LeaveEntitlementMapper $mapper) {}

    public function save(LeaveEntitlement $entitlement): void
    {
        LeaveEntitlementRecord::query()->updateOrCreate(
            ['id' => $entitlement->id->value],
            $this->mapper->toAttributes($entitlement),
        );
    }

    public function find(LeaveEntitlementId $id): ?LeaveEntitlement
    {
        $record = LeaveEntitlementRecord::query()->find($id->value);

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function findFor(string $employeeId, LeaveTypeId $leaveTypeId, int $year): ?LeaveEntitlement
    {
        $record = $this->baseQuery($employeeId, $leaveTypeId, $year)->first();

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function findForUpdate(string $employeeId, LeaveTypeId $leaveTypeId, int $year): ?LeaveEntitlement
    {
        $record = $this->baseQuery($employeeId, $leaveTypeId, $year)->lockForUpdate()->first();

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function allForEmployee(string $employeeId, int $year): array
    {
        return LeaveEntitlementRecord::query()
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->get()
            ->map(fn (LeaveEntitlementRecord $record) => $this->mapper->toDomain($record))
            ->all();
    }

    private function baseQuery(string $employeeId, LeaveTypeId $leaveTypeId, int $year)
    {
        return LeaveEntitlementRecord::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId->value)
            ->where('year', $year);
    }
}
