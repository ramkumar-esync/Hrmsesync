<?php

declare(strict_types=1);

namespace HR\Leave\Application\Query;

use Illuminate\Database\DatabaseManager;

/**
 * The number payroll asks for.
 *
 * Counts approved, deductible days of unpaid leave types that fall inside the
 * window. Because each day is stored as its own row, a leave spell that starts
 * in June and ends in July is split correctly across two payroll runs — which
 * a start/end date pair alone cannot do.
 */
final readonly class UnpaidLeaveDaysQuery
{
    public function __construct(private DatabaseManager $database) {}

    public function totalFor(string $employeeId, string $from, string $to): float
    {
        $rows = $this->database->table('leave_days as d')
            ->join('leave_applications as a', 'a.id', '=', 'd.leave_application_id')
            ->join('leave_types as t', 't.id', '=', 'a.leave_type_id')
            ->where('a.employee_id', $employeeId)
            ->where('a.status', 'approved')
            ->where('t.is_paid', false)
            ->where('d.is_deductible', true)
            ->whereBetween('d.date', [$from, $to])
            ->pluck('d.portion');

        $total = 0.0;

        foreach ($rows as $portion) {
            $total += $portion === 'full' ? 1.0 : 0.5;
        }

        return round($total, 2);
    }
}
