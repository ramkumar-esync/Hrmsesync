<?php

declare(strict_types=1);

namespace HR\Leave\Infrastructure\Persistence\Eloquent;

use HR\Leave\Domain\Entity\LeaveType;
use HR\Leave\Domain\ValueObject\AccrualPolicy;
use HR\Leave\Domain\ValueObject\LeaveTypeId;

final class LeaveTypeMapper
{
    public function toDomain(LeaveTypeRecord $record): LeaveType
    {
        return LeaveType::reconstitute(
            id: LeaveTypeId::fromString($record->id),
            code: $record->code,
            name: $record->name,
            paid: (bool) $record->is_paid,
            accrualPolicy: AccrualPolicy::from($record->accrual_policy),
            defaultEntitlementDays: (float) $record->default_entitlement_days,
            carryForwardCap: (float) $record->carry_forward_cap,
            carryForwardExpiryMonths: (int) $record->carry_forward_expiry_months,
            allowHalfDay: (bool) $record->allow_half_day,
            requiresAttachment: (bool) $record->requires_attachment,
            maxConsecutiveDays: $record->max_consecutive_days !== null
                ? (int) $record->max_consecutive_days
                : null,
            minNoticeDays: (int) $record->min_notice_days,
            allowBackdating: (bool) $record->allow_backdating,
            active: (bool) $record->is_active,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(LeaveType $type): array
    {
        return [
            'id' => $type->id->value,
            'code' => $type->code(),
            'name' => $type->name(),
            'is_paid' => $type->isPaid(),
            'accrual_policy' => $type->accrualPolicy()->value,
            'default_entitlement_days' => $type->defaultEntitlementDays(),
            'carry_forward_cap' => $type->carryForwardCap(),
            'carry_forward_expiry_months' => $type->carryForwardExpiryMonths(),
            'allow_half_day' => $type->allowsHalfDay(),
            'requires_attachment' => $type->requiresAttachment(),
            'max_consecutive_days' => $type->maxConsecutiveDays(),
            'min_notice_days' => $type->minNoticeDays(),
            'allow_backdating' => $type->allowsBackdating(),
            'is_active' => $type->isActive(),
        ];
    }
}
