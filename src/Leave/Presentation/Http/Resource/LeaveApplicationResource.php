<?php

declare(strict_types=1);

namespace HR\Leave\Presentation\Http\Resource;

use HR\Leave\Domain\Entity\LeaveApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LeaveApplicationResource extends JsonResource
{
    public function __construct(private readonly LeaveApplication $application)
    {
        parent::__construct($application);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $application = $this->application;

        return [
            'id' => $application->id->value,
            'employee_id' => $application->employeeId,
            'leave_type_id' => $application->leaveTypeId->value,
            'dates' => $application->dates(),
            'working_days' => $application->workingDays(),
            'days' => $application->days(),
            'reason' => $application->reason(),
            'contact_while_away' => $application->contactWhileAway(),
            'has_attachment' => $application->attachmentPath() !== null,
            'status' => $application->status()->value,
            'status_label' => $application->status()->label(),
            'applied_at' => $application->appliedAt()->toIso8601String(),
            'decided_at' => $application->decidedAt()?->toIso8601String(),
            'decision_note' => $application->decisionNote(),
        ];
    }
}
