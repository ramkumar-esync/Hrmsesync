<?php

declare(strict_types=1);

namespace HR\Leave\Application\Query;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/** Read model for tracking leave status — the employee's list and the approver's queue. */
final readonly class LeaveApplicationQuery
{
    public function __construct(private DatabaseManager $database) {}

    public function forEmployee(
        string $employeeId,
        ?string $status = null,
        ?int $year = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return $this->base()
            ->where('a.employee_id', $employeeId)
            ->when($status, fn (Builder $q) => $q->where('a.status', $status))
            ->when($year, fn (Builder $q) => $q->whereYear('a.start_date', $year))
            ->orderByDesc('a.start_date')
            ->paginate($perPage);
    }

    /**
     * @param  list<string>  $employeeIds
     * @return Collection<int, object>
     */
    public function pendingFor(array $employeeIds): Collection
    {
        if ($employeeIds === []) {
            return collect();
        }

        return $this->base()
            ->whereIn('a.employee_id', $employeeIds)
            ->where('a.status', 'pending')
            ->orderBy('a.start_date')
            ->get();
    }

    /** @return Collection<int, object> Who is away, for the team calendar. */
    public function approvedBetween(string $from, string $to): Collection
    {
        return $this->base()
            ->where('a.status', 'approved')
            ->where('a.start_date', '<=', $to)
            ->where('a.end_date', '>=', $from)
            ->orderBy('a.start_date')
            ->get();
    }

    private function base(): Builder
    {
        return $this->database->table('leave_applications as a')
            ->join('leave_types as t', 't.id', '=', 'a.leave_type_id')
            ->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->select([
                'a.id', 'a.status', 'a.start_date', 'a.end_date', 'a.working_days',
                'a.reason', 'a.applied_at', 'a.decided_at', 'a.decision_note',
                'a.attachment_path',
                't.code as leave_type_code', 't.name as leave_type_name', 't.is_paid',
                'e.name as employee_name', 'e.employee_number',
            ]);
    }
}
