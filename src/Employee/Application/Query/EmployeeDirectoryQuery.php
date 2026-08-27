<?php

declare(strict_types=1);

namespace HR\Employee\Application\Query;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;

/**
 * Read side. Lists and searches bypass the aggregate deliberately — rebuilding
 * hundreds of Employee objects just to render a table is wasted work.
 */
final readonly class EmployeeDirectoryQuery
{
    public function __construct(private DatabaseManager $database) {}

    public function paginate(?string $search, ?string $status, int $perPage = 25): LengthAwarePaginator
    {
        $page = $this->database->table('employees')
            ->select([
                'id', 'employee_number', 'name', 'work_email', 'job_title',
                'department', 'status', 'joined_on', 'basic_salary_minor', 'currency',
                // Needed so the directory knows who has a login account, which
                // drives the "Reset password" action. It is turned into a
                // boolean below so the raw id never reaches the client.
                'user_id',
            ])
            ->when($search, fn ($query) => $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhere('work_email', 'like', "%{$search}%");
            }))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('employee_number')
            ->paginate($perPage);

        // Expose has_login and drop the raw user_id from each row.
        $page->getCollection()->transform(function (object $row): object {
            $row->has_login = ! empty($row->user_id);
            unset($row->user_id);

            return $row;
        });

        return $page;
    }
}