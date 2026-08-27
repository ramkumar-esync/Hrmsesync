<?php

declare(strict_types=1);

namespace HR\Attendance\Domain\Repository;

use HR\Attendance\Domain\Entity\AttendanceSheet;
use HR\Attendance\Domain\ValueObject\AttendancePeriod;
use HR\Attendance\Domain\ValueObject\AttendanceSheetId;

interface AttendanceSheetRepository
{
    public function find(AttendanceSheetId $id): ?AttendanceSheet;

    public function get(AttendanceSheetId $id): AttendanceSheet;

    public function findForEmployeePeriod(string $employeeId, AttendancePeriod $period): ?AttendanceSheet;

    public function save(AttendanceSheet $sheet): void;
}
