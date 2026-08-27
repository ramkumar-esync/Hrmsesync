<?php

declare(strict_types=1);

namespace HR\Attendance\Presentation\Http\Controller;

use HR\Attendance\Application\Command\DecideAttendanceSheet;
use HR\Attendance\Application\Command\DecideAttendanceSheetHandler;
use HR\Attendance\Application\Query\AttendanceSheetQuery;
use HR\Attendance\Domain\Repository\AttendanceSheetRepository;
use HR\Attendance\Domain\ValueObject\AttendanceSheetId;
use HR\Attendance\Presentation\Http\Resource\AttendanceSheetResource;
use HR\Employee\Domain\Repository\EmployeeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HR's side: the queue of submitted sheets, one sheet in full, and the decision.
 * The route group already restricts this to HR, so there is no per-row check
 * here — every submitted sheet is HR's to review.
 */
final class AttendanceReviewController
{
    public function pending(AttendanceSheetQuery $query): JsonResponse
    {
        return response()->json(['data' => $query->awaitingDecision()]);
    }

    public function show(
        string $sheetId,
        AttendanceSheetRepository $sheets,
        EmployeeRepository $employees,
    ): JsonResponse {
        $sheet = $sheets->get(AttendanceSheetId::fromString($sheetId));
        $employee = $employees->find(
            \HR\Employee\Domain\ValueObject\EmployeeId::fromString($sheet->employeeId()),
        );

        return (new AttendanceSheetResource($sheet))
            ->additional(['meta' => [
                'employee_name' => $employee?->name()->full,
                'employee_number' => $employee?->employeeNumber()->value,
            ]])
            ->response();
    }

    public function decide(
        string $sheetId,
        Request $request,
        EmployeeRepository $employees,
        DecideAttendanceSheetHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,return'],
            // Required when returning, so the employee knows what to fix.
            'note' => ['nullable', 'string', 'max:1000', 'required_if:decision,return'],
        ]);

        $approver = $employees->findByUserId((string) $request->user()->id);

        $sheet = $handler(new DecideAttendanceSheet(
            sheetId: $sheetId,
            approverEmployeeId: $approver?->id->value ?? (string) $request->user()->id,
            approve: $validated['decision'] === 'approve',
            note: $validated['note'] ?? null,
        ));

        return (new AttendanceSheetResource($sheet))->response();
    }
}
