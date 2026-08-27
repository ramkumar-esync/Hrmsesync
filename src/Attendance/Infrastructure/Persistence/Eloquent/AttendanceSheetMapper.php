<?php

declare(strict_types=1);

namespace HR\Attendance\Infrastructure\Persistence\Eloquent;

use Carbon\CarbonImmutable;
use HR\Attendance\Domain\Entity\AttendanceSheet;
use HR\Attendance\Domain\ValueObject\AttendanceEntry;
use HR\Attendance\Domain\ValueObject\AttendancePeriod;
use HR\Attendance\Domain\ValueObject\AttendanceSheetId;
use HR\Attendance\Domain\ValueObject\SheetStatus;

/**
 * Translates between the AttendanceSheet aggregate and its table row. The rows
 * live in the JSON `entries` column as plain arrays; this is the only place that
 * knows their shape.
 */
final class AttendanceSheetMapper
{
    public function toDomain(AttendanceSheetRecord $record): AttendanceSheet
    {
        $entries = array_map(
            static fn (array $row): AttendanceEntry => new AttendanceEntry(
                date: CarbonImmutable::parse($row['date'])->startOfDay(),
                minutes: (int) $row['minutes'],
                leaveTypeCode: $row['leave_type_code'] ?? null,
                remarks: $row['remarks'] ?? null,
            ),
            $record->entries ?? [],
        );

        return AttendanceSheet::reconstitute(
            id: AttendanceSheetId::fromString($record->id),
            employeeId: $record->employee_id,
            period: AttendancePeriod::fromString($record->period),
            status: SheetStatus::from($record->status),
            entries: $entries,
            submittedAt: $record->submitted_at,
            decidedAt: $record->decided_at,
            decidedBy: $record->decided_by,
            decisionNote: $record->decision_note,
        );
    }

    /** @return array<string, mixed> */
    public function toRow(AttendanceSheet $sheet): array
    {
        $entries = array_map(
            static fn (AttendanceEntry $entry): array => [
                'date' => $entry->date->toDateString(),
                'minutes' => $entry->minutes,
                'leave_type_code' => $entry->leaveTypeCode,
                'remarks' => $entry->remarks,
            ],
            $sheet->entries(),
        );

        return [
            'id' => $sheet->id()->value,
            'employee_id' => $sheet->employeeId(),
            'period' => $sheet->period()->toString(),
            'status' => $sheet->status()->value,
            'entries' => $entries,
            'total_minutes' => $sheet->totalMinutes(),
            'submitted_at' => $sheet->submittedAt(),
            'decided_at' => $sheet->decidedAt(),
            'decided_by' => $sheet->decidedBy(),
            'decision_note' => $sheet->decisionNote(),
        ];
    }
}
