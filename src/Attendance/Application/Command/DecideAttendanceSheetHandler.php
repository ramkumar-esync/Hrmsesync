<?php

declare(strict_types=1);

namespace HR\Attendance\Application\Command;

use HR\Attendance\Domain\Entity\AttendanceSheet;
use HR\Attendance\Domain\Repository\AttendanceSheetRepository;
use HR\Attendance\Domain\ValueObject\AttendanceSheetId;
use HR\Shared\Application\TransactionManager;
use HR\Shared\Domain\Contract\Clock;

final readonly class DecideAttendanceSheetHandler
{
    public function __construct(
        private AttendanceSheetRepository $sheets,
        private TransactionManager $transaction,
        private Clock $clock,
    ) {}

    public function __invoke(DecideAttendanceSheet $command): AttendanceSheet
    {
        return $this->transaction->transactional(function () use ($command): AttendanceSheet {
            $sheet = $this->sheets->get(AttendanceSheetId::fromString($command->sheetId));

            if ($command->approve) {
                $sheet->approve($command->approverEmployeeId, $this->clock->now(), $command->note);
            } else {
                $sheet->returnForChanges(
                    $command->approverEmployeeId,
                    $this->clock->now(),
                    (string) $command->note,
                );
            }

            $this->sheets->save($sheet);

            return $sheet;
        });
    }
}
