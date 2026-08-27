<?php

declare(strict_types=1);

namespace HR\Attendance\Domain\Service;

use Carbon\CarbonImmutable;

/**
 * A port into the Leave context, so the Attendance aggregate can check its leave
 * rows without reaching into another context's tables directly.
 *
 * Given an employee and a date, it returns the short codes of the leave types
 * the employee has *approved* leave for on that date. The sheet uses this to
 * confirm that a row marked "AL" really lines up with an approved annual-leave
 * day, and to say precisely what is wrong when it does not.
 *
 * @return list<string> approved leave-type codes on that date (usually 0 or 1)
 */
interface LeaveVerifier
{
    /**
     * @param  list<CarbonImmutable>  $dates
     * @return array<string, list<string>> keyed by 'Y-m-d' → approved type codes
     */
    public function approvedLeaveCodesFor(string $employeeId, array $dates): array;
}
