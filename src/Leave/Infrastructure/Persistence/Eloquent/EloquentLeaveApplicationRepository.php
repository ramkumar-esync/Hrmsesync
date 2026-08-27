<?php

declare(strict_types=1);

namespace HR\Leave\Infrastructure\Persistence\Eloquent;

use HR\Leave\Domain\Entity\LeaveApplication;
use HR\Leave\Domain\Exception\LeaveApplicationNotFound;
use HR\Leave\Domain\Repository\LeaveApplicationRepository;
use HR\Leave\Domain\ValueObject\LeaveApplicationId;
use HR\Shared\Domain\ValueObject\DateRange;
use Illuminate\Database\DatabaseManager;

final readonly class EloquentLeaveApplicationRepository implements LeaveApplicationRepository
{
    public function __construct(
        private LeaveApplicationMapper $mapper,
        private DatabaseManager $database,
    ) {}

    public function save(LeaveApplication $application): void
    {
        LeaveApplicationRecord::query()->updateOrCreate(
            ['id' => $application->id->value],
            $this->mapper->toAttributes($application),
        );

        // The expanded days are immutable once submitted, so it is safe and
        // simpler to rewrite them wholesale.
        $this->database->table('leave_days')
            ->where('leave_application_id', $application->id->value)
            ->delete();

        $rows = $this->mapper->daysToRows($application);

        if ($rows !== []) {
            $this->database->table('leave_days')->insert($rows);
        }
    }

    public function find(LeaveApplicationId $id): ?LeaveApplication
    {
        $record = LeaveApplicationRecord::query()->with('days')->find($id->value);

        return $record ? $this->mapper->toDomain($record) : null;
    }

    public function get(LeaveApplicationId $id): LeaveApplication
    {
        return $this->find($id) ?? throw LeaveApplicationNotFound::withId($id);
    }

    public function activeOverlapping(string $employeeId, DateRange $dates): array
    {
        return LeaveApplicationRecord::query()
            ->with('days')
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $dates->end->toDateString())
            ->where('end_date', '>=', $dates->start->toDateString())
            ->get()
            ->map(fn (LeaveApplicationRecord $record) => $this->mapper->toDomain($record))
            ->all();
    }

    public function pendingForApprover(string $approverEmployeeId): array
    {
        return LeaveApplicationRecord::query()
            ->with('days')
            ->where('status', 'pending')
            ->whereIn('employee_id', function ($query) use ($approverEmployeeId): void {
                $query->select('id')->from('employees')->where('reports_to', $approverEmployeeId);
            })
            ->orderBy('start_date')
            ->get()
            ->map(fn (LeaveApplicationRecord $record) => $this->mapper->toDomain($record))
            ->all();
    }
}
