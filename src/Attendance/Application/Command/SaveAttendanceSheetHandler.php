<?php

declare(strict_types=1);

namespace HR\Attendance\Application\Command;

use Carbon\CarbonImmutable;
use HR\Attendance\Domain\Entity\AttendanceSheet;
use HR\Attendance\Domain\Repository\AttendanceSheetRepository;
use HR\Attendance\Domain\ValueObject\AttendanceEntry;
use HR\Attendance\Domain\ValueObject\AttendancePeriod;
use HR\Attendance\Domain\ValueObject\AttendanceSheetId;
use HR\Shared\Application\TransactionManager;

final readonly class SaveAttendanceSheetHandler
{
    public function __construct(
        private AttendanceSheetRepository $sheets,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(SaveAttendanceSheet $command): AttendanceSheet
    {
        $period = AttendancePeriod::fromString($command->period);

        return $this->transaction->transactional(function () use ($command, $period): AttendanceSheet {
            $sheet = $this->sheets->findForEmployeePeriod($command->employeeId, $period)
                ?? AttendanceSheet::start(AttendanceSheetId::generate(), $command->employeeId, $period);

            $entries = array_map(
                static fn (array $row): AttendanceEntry => AttendanceEntry::fromHours(
                    date: CarbonImmutable::parse($row['date']),
                    hours: (float) ($row['hours'] ?? 0),
                    leaveTypeCode: $row['leave_type_code'] ?? null,
                    remarks: $row['remarks'] ?? null,
                ),
                $command->entries,
            );

            $sheet->replaceEntries($entries);
            $this->sheets->save($sheet);

            return $sheet;
        });
    }
}
