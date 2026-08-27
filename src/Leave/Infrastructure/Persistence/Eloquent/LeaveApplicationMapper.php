<?php

declare(strict_types=1);

namespace HR\Leave\Infrastructure\Persistence\Eloquent;

use Carbon\CarbonImmutable;
use HR\Leave\Domain\Entity\LeaveApplication;
use HR\Leave\Domain\ValueObject\DayPortion;
use HR\Leave\Domain\ValueObject\LeaveApplicationId;
use HR\Leave\Domain\ValueObject\LeaveDay;
use HR\Leave\Domain\ValueObject\LeaveStatus;
use HR\Leave\Domain\ValueObject\LeaveTypeId;
use HR\Shared\Domain\ValueObject\DateRange;

final class LeaveApplicationMapper
{
    public function toDomain(LeaveApplicationRecord $record): LeaveApplication
    {
        $days = $record->relationLoaded('days')
            ? $record->days
            : $record->days()->orderBy('date')->get();

        return LeaveApplication::reconstitute(
            id: LeaveApplicationId::fromString($record->id),
            employeeId: $record->employee_id,
            leaveTypeId: LeaveTypeId::fromString($record->leave_type_id),
            entitlementYear: (int) $record->entitlement_year,
            dates: new DateRange(
                CarbonImmutable::parse($record->start_date),
                CarbonImmutable::parse($record->end_date),
            ),
            days: $days->map(static fn (LeaveDayRecord $day) => new LeaveDay(
                CarbonImmutable::parse($day->date),
                DayPortion::from($day->portion),
                (bool) $day->is_deductible,
                $day->non_working_reason,
            ))->all(),
            workingDays: (float) $record->working_days,
            reason: (string) $record->reason,
            attachmentPath: $record->attachment_path,
            contactWhileAway: $record->contact_while_away,
            status: LeaveStatus::from($record->status),
            appliedAt: CarbonImmutable::parse($record->applied_at),
            decidedBy: $record->decided_by,
            decidedAt: $record->decided_at ? CarbonImmutable::parse($record->decided_at) : null,
            decisionNote: $record->decision_note,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(LeaveApplication $application): array
    {
        return [
            'id' => $application->id->value,
            'employee_id' => $application->employeeId,
            'leave_type_id' => $application->leaveTypeId->value,
            'entitlement_year' => $application->entitlementYear,
            'start_date' => $application->dates()->start->toDateString(),
            'end_date' => $application->dates()->end->toDateString(),
            'working_days' => $application->workingDays(),
            'reason' => $application->reason(),
            'attachment_path' => $application->attachmentPath(),
            'contact_while_away' => $application->contactWhileAway(),
            'status' => $application->status()->value,
            'applied_at' => $application->appliedAt()->toDateTimeString(),
            'decided_by' => $application->decidedBy(),
            'decided_at' => $application->decidedAt()?->toDateTimeString(),
            'decision_note' => $application->decisionNote(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function daysToRows(LeaveApplication $application): array
    {
        return array_map(
            static fn (LeaveDay $day) => [
                'leave_application_id' => $application->id->value,
                'date' => $day->date->toDateString(),
                'portion' => $day->portion->value,
                'is_deductible' => $day->deductible,
                'non_working_reason' => $day->nonWorkingReason,
            ],
            $application->days(),
        );
    }
}
