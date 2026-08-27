<?php

declare(strict_types=1);

namespace HR\Attendance\Application\Command;

use HR\Attendance\Domain\Entity\AttendanceSheet;
use HR\Attendance\Domain\Exception\AttendanceSheetNotFound;
use HR\Attendance\Domain\Repository\AttendanceSheetRepository;
use HR\Attendance\Domain\Service\LeaveVerifier;
use HR\Attendance\Domain\ValueObject\AttendancePeriod;
use HR\Shared\Application\TransactionManager;
use HR\Shared\Domain\Contract\Clock;

final readonly class SubmitAttendanceSheetHandler
{
    public function __construct(
        private AttendanceSheetRepository $sheets,
        private LeaveVerifier $leave,
        private TransactionManager $transaction,
        private Clock $clock,
    ) {}

    public function __invoke(SubmitAttendanceSheet $command): AttendanceSheet
    {
        $period = AttendancePeriod::fromString($command->period);

        return $this->transaction->transactional(function () use ($command, $period): AttendanceSheet {
            $sheet = $this->sheets->findForEmployeePeriod($command->employeeId, $period)
                ?? throw AttendanceSheetNotFound::forPeriod($command->employeeId, $command->period);

            $sheet->submit($this->leave, $this->clock->now());
            $this->sheets->save($sheet);

            return $sheet;
        });
    }
}
