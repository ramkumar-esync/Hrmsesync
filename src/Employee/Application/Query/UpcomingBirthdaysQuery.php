<?php

declare(strict_types=1);

namespace HR\Employee\Application\Query;

use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;

/**
 * Upcoming birthdays among current staff, for the HR and manager dashboards.
 *
 * Only the month and day drive "upcoming", so the year is ignored and the wrap
 * around New Year is handled in PHP rather than in date SQL that differs between
 * MySQL and SQLite. Age is deliberately not returned — the dashboard shows who
 * and when, not how old.
 */
final readonly class UpcomingBirthdaysQuery
{
    public function __construct(private DatabaseManager $database) {}

    /**
     * @return list<array{name:string,employee_number:string,department:?string,date:string,in_days:int}>
     */
    public function withinDays(int $days = 30): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        $rows = $this->database->table('employees')
            ->whereNull('left_on')
            ->whereNotIn('status', ['resigned', 'terminated'])
            ->get(['name', 'employee_number', 'department', 'date_of_birth']);

        $upcoming = [];

        foreach ($rows as $row) {
            if (empty($row->date_of_birth)) {
                continue;
            }

            $dob = CarbonImmutable::parse($row->date_of_birth);

            // This year's birthday; if it has passed, roll to next year.
            $next = $dob->setYear($today->year)->startOfDay();
            if ($next->lessThan($today)) {
                $next = $next->addYear();
            }

            $inDays = $today->diffInDays($next);

            if ($inDays <= $days) {
                $upcoming[] = [
                    'name' => $row->name,
                    'employee_number' => $row->employee_number,
                    'department' => $row->department,
                    'date' => $next->toDateString(),
                    'in_days' => $inDays,
                ];
            }
        }

        usort($upcoming, static fn ($a, $b) => $a['in_days'] <=> $b['in_days']);

        return $upcoming;
    }
}
