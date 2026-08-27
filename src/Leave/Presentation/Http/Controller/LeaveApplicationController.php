<?php

declare(strict_types=1);

namespace HR\Leave\Presentation\Http\Controller;

use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Leave\Application\Command\ApplyForLeave;
use HR\Leave\Application\Command\ApplyForLeaveHandler;
use HR\Leave\Application\Command\CancelLeaveApplication;
use HR\Leave\Application\Command\CancelLeaveApplicationHandler;
use HR\Leave\Application\Query\LeaveApplicationQuery;
use HR\Leave\Domain\Repository\LeaveApplicationRepository;
use HR\Leave\Domain\ValueObject\LeaveApplicationId;
use HR\Leave\Presentation\Http\Request\ApplyForLeaveRequest;
use HR\Leave\Presentation\Http\Resource\LeaveApplicationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Applying for leave and tracking what happened to the request. */
final class LeaveApplicationController
{
    public function index(
        Request $request,
        EmployeeRepository $employees,
        LeaveApplicationQuery $query,
    ): JsonResponse {
        $employee = $employees->findByUserId((string) $request->user()->id);

        if ($employee === null) {
            return response()->json([
                'message' => 'This account is not linked to an employee record. Contact HR.',
            ], 404);
        }

        return response()->json($query->forEmployee(
            employeeId: $employee->id->value,
            status: $request->query('status'),
            year: $request->has('year') ? (int) $request->query('year') : null,
        ));
    }

    public function show(
        string $applicationId,
        LeaveApplicationRepository $applications,
    ): LeaveApplicationResource {
        $application = $applications->get(LeaveApplicationId::fromString($applicationId));

        Gate::authorize('view', $application);

        return new LeaveApplicationResource($application);
    }

    public function store(
        ApplyForLeaveRequest $request,
        EmployeeRepository $employees,
        ApplyForLeaveHandler $handler,
        \HR\Leave\Domain\Repository\LeaveTypeRepository $leaveTypes,
    ): JsonResponse {
        $employee = $employees->findByUserId((string) $request->user()->id);

        if ($employee === null) {
            return response()->json([
                'message' => 'This account is not linked to an employee record. Contact HR.',
            ], 404);
        }

        // Attachments go to the private disk — medical certificates are sensitive.
        $attachmentPath = $request->hasFile('attachment')
            ? $request->file('attachment')->store('leave-attachments', ['disk' => 'local'])
            : null;

        $application = $handler(new ApplyForLeave(
            employeeId: $employee->id->value,
            leaveTypeId: $request->string('leave_type_id')->value(),
            startDate: $request->string('start_date')->value(),
            endDate: $request->string('end_date')->value(),
            reason: $request->string('reason')->value(),
            startPortion: $request->string('start_portion', 'full')->value(),
            endPortion: $request->string('end_portion', 'full')->value(),
            attachmentPath: $attachmentPath,
            contactWhileAway: $request->input('contact_while_away'),
        ));

        $this->notifyApprovers($application, $employee, $leaveTypes);

        return (new LeaveApplicationResource($application))->response()->setStatusCode(201);
    }

    public function cancel(
        string $applicationId,
        Request $request,
        LeaveApplicationRepository $applications,
        CancelLeaveApplicationHandler $handler,
    ): LeaveApplicationResource {
        $application = $applications->get(LeaveApplicationId::fromString($applicationId));

        Gate::authorize('cancel', $application);

        return new LeaveApplicationResource($handler(new CancelLeaveApplication(
            applicationId: $applicationId,
            cancelledBy: (string) $request->user()->employeeId(),
            // Only HR may unwind leave that has already begun.
            allowAfterStart: $request->user()->role->canApproveLeaveForAnyone(),
        )));
    }

    /**
     * Email HR that a leave request is waiting. Best-effort: any failure is
     * logged and swallowed so a mail problem never blocks the application, which
     * is already saved by the time we get here.
     */
    private function notifyApprovers(
        object $application,
        object $employee,
        \HR\Leave\Domain\Repository\LeaveTypeRepository $leaveTypes,
    ): void {
        try {
            $type = $leaveTypes->find($application->leaveTypeId);
            $range = $application->dates();

            $dates = $range->start->format('d M Y').' – '.$range->end->format('d M Y');

            // Everyone with the HR admin role receives the notification.
            $recipients = \Illuminate\Support\Facades\DB::table('users')
                ->where('role', 'hr_admin')
                ->where('is_active', true)
                ->pluck('email')
                ->all();

            if ($recipients === []) {
                return;
            }

            \Illuminate\Support\Facades\Mail::to($recipients)->send(
                new \App\Mail\LeaveAppliedMail(
                    employeeName: $employee->name()->full,
                    leaveType: $type?->name() ?? 'Leave',
                    dates: $dates,
                    workingDays: $application->workingDays(),
                    reason: $application->reason(),
                    appUrl: rtrim((string) config('app.url'), '/'),
                ),
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Leave notification email could not be sent.', [
                'application' => $application->id->value ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}