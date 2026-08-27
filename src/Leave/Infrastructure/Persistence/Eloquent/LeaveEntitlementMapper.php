<?php

declare(strict_types=1);

namespace HR\Leave\Infrastructure\Persistence\Eloquent;

use HR\Leave\Domain\Entity\LeaveEntitlement;
use HR\Leave\Domain\ValueObject\LeaveEntitlementId;
use HR\Leave\Domain\ValueObject\LeaveTypeId;

final class LeaveEntitlementMapper
{
    public function toDomain(LeaveEntitlementRecord $record): LeaveEntitlement
    {
        return LeaveEntitlement::reconstitute(
            id: LeaveEntitlementId::fromString($record->id),
            employeeId: $record->employee_id,
            leaveTypeId: LeaveTypeId::fromString($record->leave_type_id),
            year: (int) $record->year,
            entitledDays: (float) $record->entitled_days,
            carriedForwardDays: (float) $record->carried_forward_days,
            adjustmentDays: (float) $record->adjustment_days,
            takenDays: (float) $record->taken_days,
            pendingDays: (float) $record->pending_days,
            carryForwardExpiresOn: $record->carry_forward_expires_on,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(LeaveEntitlement $entitlement): array
    {
        $balance = $entitlement->balance();

        return [
            'id' => $entitlement->id->value,
            'employee_id' => $entitlement->employeeId,
            'leave_type_id' => $entitlement->leaveTypeId->value,
            'year' => $entitlement->year,
            'entitled_days' => $balance->entitled,
            'carried_forward_days' => $balance->carriedForward,
            'adjustment_days' => $balance->adjustment,
            'taken_days' => $balance->taken,
            'pending_days' => $balance->pending,
            'carry_forward_expires_on' => $entitlement->carryForwardExpiresOn(),
        ];
    }
}
