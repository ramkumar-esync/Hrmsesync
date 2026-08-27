<?php

declare(strict_types=1);

namespace HR\Attendance\Infrastructure\Persistence\Eloquent;

use HR\Attendance\Domain\Entity\AttendanceSheet;
use HR\Attendance\Domain\Exception\AttendanceSheetNotFound;
use HR\Attendance\Domain\Repository\AttendanceSheetRepository;
use HR\Attendance\Domain\ValueObject\AttendancePeriod;
use HR\Attendance\Domain\ValueObject\AttendanceSheetId;

final readonly class EloquentAttendanceSheetRepository implements AttendanceSheetRepository
{
    public function __construct(private AttendanceSheetMapper $mapper) {}

    public function find(AttendanceSheetId $id): ?AttendanceSheet
    {
        $record = AttendanceSheetRecord::query()->find($id->value);

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    public function get(AttendanceSheetId $id): AttendanceSheet
    {
        return $this->find($id) ?? throw AttendanceSheetNotFound::withId($id->value);
    }

    public function findForEmployeePeriod(string $employeeId, AttendancePeriod $period): ?AttendanceSheet
    {
        $record = AttendanceSheetRecord::query()
            ->where('employee_id', $employeeId)
            ->where('period', $period->toString())
            ->first();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    public function save(AttendanceSheet $sheet): void
    {
        $row = $this->mapper->toRow($sheet);

        AttendanceSheetRecord::query()->updateOrCreate(['id' => $row['id']], $row);
    }
}
