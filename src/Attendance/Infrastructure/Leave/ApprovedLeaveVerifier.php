<?php

declare(strict_types=1);

namespace HR\Attendance\Infrastructure\Leave;

use Carbon\CarbonImmutable;
use HR\Attendance\Domain\Service\LeaveVerifier;
use Illuminate\Support\Facades\DB;

/**
 * Answers the reconciliation question by reading the Leave context's tables:
 * for the given employee and dates, which leave-type codes do they have an
 * approved leave day for?
 *
 * It joins leave_days → leave_applications (approved only) → leave_types, and is
 * the single seam where Attendance depends on Leave's storage. If Leave changed
 * its schema, this adapter is the one place that would need updating.
 */
final class ApprovedLeaveVerifier implements LeaveVerifier
{
    public function approvedLeaveCodesFor(string $employeeId, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $dateStrings = array_values(array_unique(array_map(
            static fn (CarbonImmutable $d) => $d->toDateString(),
            $dates,
        )));

        $rows = DB::table('leave_days as d')
            ->join('leave_applications as a', 'a.id', '=', 'd.leave_application_id')
            ->join('leave_types as t', 't.id', '=', 'a.leave_type_id')
            ->where('a.employee_id', $employeeId)
            ->where('a.status', 'approved')
            ->whereIn('d.date', $dateStrings)
            ->get(['d.date as date', 't.code as code']);

        $map = [];
        foreach ($rows as $row) {
            $key = CarbonImmutable::parse($row->date)->toDateString();
            $map[$key][] = $row->code;
        }

        return $map;
    }
}
