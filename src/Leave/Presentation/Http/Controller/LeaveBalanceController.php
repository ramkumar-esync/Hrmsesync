<?php

declare(strict_types=1);

namespace HR\Leave\Presentation\Http\Controller;

use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Leave\Application\Command\AdjustEntitlement;
use HR\Leave\Application\Command\AdjustEntitlementHandler;
use HR\Leave\Application\Command\GrantAnnualEntitlements;
use HR\Leave\Application\Command\GrantAnnualEntitlementsHandler;
use HR\Leave\Application\Query\LeaveBalanceQuery;
use HR\Leave\Domain\Repository\LeaveTypeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LeaveBalanceController
{
    /** The employee's own balances. */
    public function mine(
        Request $request,
        EmployeeRepository $employees,
        LeaveBalanceQuery $query,
    ): JsonResponse {
        $employee = $employees->findByUserId((string) $request->user()->id);

        if ($employee === null) {
            return response()->json([
                'message' => 'This account is not linked to an employee record. Contact HR.',
            ], 404);
        }

        $year = (int) $request->query('year', now()->year);

        return response()->json([
            'year' => $year,
            'data' => $query->forEmployee($employee->id->value, $year),
        ]);
    }

    /** HR looking at someone else's balances. */
    public function forEmployee(
        string $employeeId,
        Request $request,
        LeaveBalanceQuery $query,
    ): JsonResponse {
        $year = (int) $request->query('year', now()->year);

        return response()->json([
            'year' => $year,
            'data' => $query->forEmployee($employeeId, $year),
        ]);
    }

    public function types(LeaveTypeRepository $leaveTypes): JsonResponse
    {
        return response()->json([
            'data' => array_map(static fn ($type) => [
                'id' => $type->id->value,
                'code' => $type->code(),
                'name' => $type->name(),
                'is_paid' => $type->isPaid(),
                'allows_half_day' => $type->allowsHalfDay(),
                'requires_attachment' => $type->requiresAttachment(),
                'min_notice_days' => $type->minNoticeDays(),
                'max_consecutive_days' => $type->maxConsecutiveDays(),
                'tracks_balance' => $type->tracksBalance(),
            ], $leaveTypes->allActive()),
        ]);
    }

    public function grant(Request $request, GrantAnnualEntitlementsHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'employee_id' => ['nullable', 'uuid', 'exists:employees,id'],
        ]);

        $granted = $handler(new GrantAnnualEntitlements(
            year: (int) $validated['year'],
            employeeId: $validated['employee_id'] ?? null,
        ));

        return response()->json([
            'message' => "Granted {$granted} entitlement records.",
            'granted' => $granted,
        ]);
    }

    public function adjust(Request $request, AdjustEntitlementHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'leave_type_id' => ['required', 'uuid', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'days' => ['required', 'numeric', 'between:-365,365', 'not_in:0'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $entitlement = $handler(new AdjustEntitlement(
            employeeId: $validated['employee_id'],
            leaveTypeId: $validated['leave_type_id'],
            year: (int) $validated['year'],
            days: (float) $validated['days'],
            reason: $validated['reason'],
        ));

        return response()->json([
            'message' => 'Balance adjusted.',
            'balance' => $entitlement->balance(),
        ]);
    }
}
