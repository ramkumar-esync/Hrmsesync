<?php

declare(strict_types=1);

namespace HR\Attendance\Presentation\Http\Controller;

use HR\Attendance\Application\Command\SaveAttendanceSheet;
use HR\Attendance\Application\Command\SaveAttendanceSheetHandler;
use HR\Attendance\Application\Command\SubmitAttendanceSheet;
use HR\Attendance\Application\Command\SubmitAttendanceSheetHandler;
use HR\Attendance\Domain\Repository\AttendanceSheetRepository;
use HR\Attendance\Domain\ValueObject\AttendancePeriod;
use HR\Attendance\Presentation\Http\Request\SaveAttendanceRequest;
use HR\Attendance\Presentation\Http\Resource\AttendanceSheetResource;
use HR\Employee\Domain\Repository\EmployeeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The employee's own attendance sheet: read the month, save rows, submit to HR.
 */
final class AttendanceController
{
    public function show(
        Request $request,
        EmployeeRepository $employees,
        AttendanceSheetRepository $sheets,
    ): JsonResponse {
        $employee = $employees->findByUserId((string) $request->user()->id);

        if ($employee === null) {
            return $this->noEmployee();
        }

        $period = $this->period($request);
        $sheet = $sheets->findForEmployeePeriod($employee->id->value, $period);

        if ($sheet === null) {
            // No sheet started yet: return an empty shape for this period so the
            // client can render a blank editable month without a special case.
            return response()->json([
                'data' => [
                    'id' => null,
                    'period' => $period->toString(),
                    'period_label' => $period->label(),
                    'status' => 'draft',
                    'status_label' => 'Draft',
                    'editable' => true,
                    'total_hours' => 0,
                    'entries' => [],
                    'submitted_at' => null,
                    'decided_at' => null,
                    'decision_note' => null,
                ],
            ]);
        }

        return (new AttendanceSheetResource($sheet))->response();
    }

    public function save(
        SaveAttendanceRequest $request,
        EmployeeRepository $employees,
        SaveAttendanceSheetHandler $handler,
    ): JsonResponse {
        $employee = $employees->findByUserId((string) $request->user()->id);

        if ($employee === null) {
            return $this->noEmployee();
        }

        $sheet = $handler(new SaveAttendanceSheet(
            employeeId: $employee->id->value,
            period: $request->validated('period'),
            entries: $request->validated('entries', []),
        ));

        return (new AttendanceSheetResource($sheet))->response();
    }

    public function submit(
        Request $request,
        EmployeeRepository $employees,
        SubmitAttendanceSheetHandler $handler,
    ): JsonResponse {
        $employee = $employees->findByUserId((string) $request->user()->id);

        if ($employee === null) {
            return $this->noEmployee();
        }

        $validated = $request->validate(['period' => ['required', 'string']]);

        $sheet = $handler(new SubmitAttendanceSheet(
            employeeId: $employee->id->value,
            period: $validated['period'],
        ));

        return (new AttendanceSheetResource($sheet))->response();
    }

    private function period(Request $request): AttendancePeriod
    {
        $value = $request->query('period');

        if (is_string($value) && $value !== '') {
            return AttendancePeriod::fromString($value);
        }

        $now = now();

        return new AttendancePeriod((int) $now->year, (int) $now->month);
    }

    private function noEmployee(): JsonResponse
    {
        return response()->json([
            'message' => 'This account is not linked to an employee record. Contact HR.',
        ], 404);
    }
}
