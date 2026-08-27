<?php

declare(strict_types=1);

namespace HR\Attendance\Presentation\Http\Resource;

use HR\Attendance\Domain\Entity\AttendanceSheet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendanceSheet
 */
final class AttendanceSheetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AttendanceSheet $sheet */
        $sheet = $this->resource;

        return [
            'id' => $sheet->id()->value,
            'period' => $sheet->period()->toString(),
            'period_label' => $sheet->period()->label(),
            'status' => $sheet->status()->value,
            'status_label' => $sheet->status()->label(),
            'editable' => $sheet->status()->isEditable(),
            'total_hours' => round($sheet->totalMinutes() / 60, 2),
            'entries' => array_map(
                static fn ($entry) => [
                    'date' => $entry->date->toDateString(),
                    'day' => $entry->day(),
                    'hours' => $entry->hours(),
                    'leave_type_code' => $entry->leaveTypeCode,
                    'remarks' => $entry->remarks,
                ],
                $sheet->entries(),
            ),
            'submitted_at' => $sheet->submittedAt()?->toIso8601String(),
            'decided_at' => $sheet->decidedAt()?->toIso8601String(),
            'decision_note' => $sheet->decisionNote(),
        ];
    }
}
