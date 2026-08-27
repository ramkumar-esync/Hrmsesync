<?php

declare(strict_types=1);

namespace HR\Leave\Application\Query;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/** Powers the "my leave balances" screen. */
final readonly class LeaveBalanceQuery
{
    public function __construct(private DatabaseManager $database) {}

    /** @return Collection<int, object> */
    public function forEmployee(string $employeeId, int $year): Collection
    {
        return $this->database->table('leave_entitlements as e')
            ->join('leave_types as t', 't.id', '=', 'e.leave_type_id')
            ->select([
                'e.id', 't.code', 't.name', 't.is_paid', 't.accrual_policy',
                'e.entitled_days', 'e.carried_forward_days', 'e.adjustment_days',
                'e.taken_days', 'e.pending_days', 'e.carry_forward_expires_on',
            ])
            ->where('e.employee_id', $employeeId)
            ->where('e.year', $year)
            ->orderBy('t.name')
            ->get()
            ->map(static function (object $row): object {
                $granted = round(
                    (float) $row->entitled_days
                    + (float) $row->carried_forward_days
                    + (float) $row->adjustment_days,
                    2,
                );

                return (object) [
                    'entitlement_id' => $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'is_paid' => (bool) $row->is_paid,
                    'accrual_policy' => $row->accrual_policy,
                    'entitled' => (float) $row->entitled_days,
                    'carried_forward' => (float) $row->carried_forward_days,
                    'adjustment' => (float) $row->adjustment_days,
                    'granted' => $granted,
                    'taken' => (float) $row->taken_days,
                    'pending' => (float) $row->pending_days,
                    'available' => round($granted - (float) $row->taken_days - (float) $row->pending_days, 2),
                    'carry_forward_expires_on' => $row->carry_forward_expires_on,
                ];
            });
    }
}
