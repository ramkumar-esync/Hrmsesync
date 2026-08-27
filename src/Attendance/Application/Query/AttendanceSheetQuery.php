<?php

declare(strict_types=1);

namespace HR\Attendance\Application\Query;

use HR\Attendance\Domain\ValueObject\SheetStatus;
use Illuminate\Support\Facades\DB;

/**
 * Read-side queries for attendance, kept separate from the write model. These
 * return plain arrays shaped for the API, joining to employees for names.
 */
final class AttendanceSheetQuery
{
    /**
     * Sheets awaiting an HR decision, oldest submission first so the queue is
     * fair.
     *
     * @return list<array<string, mixed>>
     */
    public function awaitingDecision(): array
    {
        return DB::table('attendance_sheets as s')
            ->join('employees as e', 'e.id', '=', 's.employee_id')
            ->where('s.status', SheetStatus::Submitted->value)
            ->orderBy('s.submitted_at')
            ->get([
                's.id', 's.period', 's.status', 's.total_minutes', 's.submitted_at',
                'e.name as employee_name', 'e.employee_number',
            ])
            ->map(static fn ($row) => [
                'id' => $row->id,
                'period' => $row->period,
                'status' => $row->status,
                'total_hours' => round(((int) $row->total_minutes) / 60, 2),
                'submitted_at' => $row->submitted_at,
                'employee_name' => $row->employee_name,
                'employee_number' => $row->employee_number,
            ])
            ->all();
    }
}
