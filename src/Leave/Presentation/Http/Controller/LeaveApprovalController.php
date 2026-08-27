<?php

declare(strict_types=1);

namespace HR\Leave\Presentation\Http\Controller;

use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Leave\Application\Command\DecideLeaveApplication;
use HR\Leave\Application\Command\DecideLeaveApplicationHandler;
use HR\Leave\Application\Query\LeaveApplicationQuery;
use HR\Leave\Domain\Repository\LeaveApplicationRepository;
use HR\Leave\Domain\ValueObject\LeaveApplicationId;
use HR\Leave\Presentation\Http\Request\DecideLeaveRequest;
use HR\Leave\Presentation\Http\Resource\LeaveApplicationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The approver's queue. */
final class LeaveApprovalController
{
    public function pending(
        Request $request,
        EmployeeRepository $employees,
        LeaveApplicationQuery $query,
    ): JsonResponse {
        $user = $request->user();
        $employeeId = $user->employeeId();

        if ($employeeId === null) {
            return response()->json(['data' => []]);
        }

        // HR sees the whole organisation; a manager sees their direct reports.
        $scope = $user->role->canApproveLeaveForAnyone()
            ? $employees->activeDuring(
                new \DateTimeImmutable('-1 year'),
                new \DateTimeImmutable('+1 year'),
            )
            : null;

        $employeeIds = $scope !== null
            ? array_map(static fn ($employee) => $employee->id->value, $scope)
            : array_map(
                static fn (EmployeeId $id) => $id->value,
                $employees->directReportsOf(EmployeeId::fromString($employeeId)),
            );

        // Nobody approves their own leave. An HR admin's request is decided by
        // another HR admin; removing the approver's own id here keeps it out of
        // their queue, and decide() enforces the same rule server-side.
        $employeeIds = array_values(array_filter(
            $employeeIds,
            static fn (string $id) => $id !== $employeeId,
        ));

        return response()->json(['data' => $query->pendingFor($employeeIds)]);
    }

    public function decide(
        string $applicationId,
        DecideLeaveRequest $request,
        LeaveApplicationRepository $applications,
        DecideLeaveApplicationHandler $handler,
        EmployeeRepository $employees,
        \HR\Leave\Domain\Repository\LeaveTypeRepository $leaveTypes,
    ): LeaveApplicationResource {
        $application = $applications->get(LeaveApplicationId::fromString($applicationId));

        Gate::authorize('decide', $application);

        $decided = $handler(new DecideLeaveApplication(
            applicationId: $applicationId,
            approverEmployeeId: (string) $request->user()->employeeId(),
            approve: $request->string('decision')->value() === 'approve',
            note: $request->input('note'),
        ));

        $this->notifyEmployee($decided, $employees, $leaveTypes);

        return new LeaveApplicationResource($decided);
    }

    /**
     * Email the employee the outcome of their leave request. Best-effort: any
     * failure is logged and swallowed so a mail problem never fails the
     * decision, which is already saved.
     */
    private function notifyEmployee(
        object $application,
        EmployeeRepository $employees,
        \HR\Leave\Domain\Repository\LeaveTypeRepository $leaveTypes,
    ): void {
        try {
            $employee = $employees->find(EmployeeId::fromString($application->employeeId));

            if ($employee === null) {
                return;
            }

            $type = $leaveTypes->find($application->leaveTypeId);
            $range = $application->dates();

            \Illuminate\Support\Facades\Mail::to($employee->workEmail())->send(
                new \App\Mail\LeaveDecidedMail(
                    name: $employee->name()->full,
                    approved: $application->status()->value === 'approved',
                    leaveType: $type?->name() ?? 'Leave',
                    dates: $range->start->format('d M Y').' – '.$range->end->format('d M Y'),
                    note: $application->decisionNote(),
                    appUrl: rtrim((string) config('app.url'), '/'),
                ),
            );

            // In-app notification, surfaced next time the employee opens the app.
            $approved = $application->status()->value === 'approved';
            \App\Support\Notifier::push(
                userId: $employee->userId(),
                type: $approved ? 'leave.approved' : 'leave.rejected',
                title: $approved ? 'Leave approved' : 'Leave not approved',
                body: ($type?->name() ?? 'Leave').', '
                    .$range->start->format('d M').' – '.$range->end->format('d M Y')
                    .($application->decisionNote() ? ' — '.$application->decisionNote() : ''),
                actionUrl: '/leave',
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Leave decision email could not be sent.', [
                'application' => $application->id->value ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function calendar(Request $request, LeaveApplicationQuery $query): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return response()->json([
            'data' => $query->approvedBetween($validated['from'], $validated['to']),
        ]);
    }
}
