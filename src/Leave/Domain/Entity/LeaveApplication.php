<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Entity;

use Carbon\CarbonImmutable;
use HR\Leave\Domain\Event\LeaveApplicationApproved;
use HR\Leave\Domain\Event\LeaveApplicationCancelled;
use HR\Leave\Domain\Event\LeaveApplicationRejected;
use HR\Leave\Domain\Event\LeaveApplicationSubmitted;
use HR\Leave\Domain\Exception\LeaveApplicationNotOpen;
use HR\Leave\Domain\ValueObject\DayPortion;
use HR\Leave\Domain\ValueObject\LeaveApplicationId;
use HR\Leave\Domain\ValueObject\LeaveDay;
use HR\Leave\Domain\ValueObject\LeaveStatus;
use HR\Leave\Domain\ValueObject\LeaveTypeId;
use HR\Shared\Domain\Event\RecordsDomainEvents;
use HR\Shared\Domain\Exception\InvariantViolation;
use HR\Shared\Domain\ValueObject\DateRange;

/**
 * An employee's request for time off, and its journey to a decision.
 *
 * The expanded day list is computed once, at submission, and then frozen. If
 * the public holiday calendar is corrected next month, an already-approved
 * application keeps the day count both parties agreed to.
 */
final class LeaveApplication
{
    use RecordsDomainEvents;

    /** @param list<LeaveDay> $days */
    private function __construct(
        public readonly LeaveApplicationId $id,
        public readonly string $employeeId,
        public readonly LeaveTypeId $leaveTypeId,
        public readonly int $entitlementYear,
        private DateRange $dates,
        private array $days,
        private float $workingDays,
        private string $reason,
        private ?string $attachmentPath,
        private ?string $contactWhileAway,
        private LeaveStatus $status,
        private CarbonImmutable $appliedAt,
        private ?string $decidedBy,
        private ?CarbonImmutable $decidedAt,
        private ?string $decisionNote,
    ) {}

    /** @param list<LeaveDay> $days */
    public static function submit(
        string $employeeId,
        LeaveTypeId $leaveTypeId,
        DateRange $dates,
        array $days,
        string $reason,
        CarbonImmutable $appliedAt,
        ?string $attachmentPath = null,
        ?string $contactWhileAway = null,
        ?LeaveApplicationId $id = null,
    ): self {
        if ($days === []) {
            throw InvariantViolation::because('A leave application needs at least one day.');
        }

        $workingDays = round(array_sum(array_map(
            static fn (LeaveDay $day) => $day->days(),
            $days,
        )), 2);

        if ($workingDays <= 0) {
            throw InvariantViolation::because(
                'Those dates contain no working days — they are all weekends or public holidays.'
            );
        }

        $application = new self(
            id: $id ?? LeaveApplicationId::generate(),
            employeeId: $employeeId,
            leaveTypeId: $leaveTypeId,
            entitlementYear: $dates->start->year,
            dates: $dates,
            days: array_values($days),
            workingDays: $workingDays,
            reason: trim($reason),
            attachmentPath: $attachmentPath,
            contactWhileAway: $contactWhileAway,
            status: LeaveStatus::Pending,
            appliedAt: $appliedAt,
            decidedBy: null,
            decidedAt: null,
            decisionNote: null,
        );

        $application->recordThat(new LeaveApplicationSubmitted(
            applicationId: $application->id,
            employeeId: $employeeId,
            leaveTypeId: $leaveTypeId,
            workingDays: $workingDays,
            occurredAt: $appliedAt,
        ));

        return $application;
    }

    /** @param list<LeaveDay> $days */
    public static function reconstitute(
        LeaveApplicationId $id,
        string $employeeId,
        LeaveTypeId $leaveTypeId,
        int $entitlementYear,
        DateRange $dates,
        array $days,
        float $workingDays,
        string $reason,
        ?string $attachmentPath,
        ?string $contactWhileAway,
        LeaveStatus $status,
        CarbonImmutable $appliedAt,
        ?string $decidedBy,
        ?CarbonImmutable $decidedAt,
        ?string $decisionNote,
    ): self {
        return new self(
            $id, $employeeId, $leaveTypeId, $entitlementYear, $dates, $days, $workingDays,
            $reason, $attachmentPath, $contactWhileAway, $status, $appliedAt,
            $decidedBy, $decidedAt, $decisionNote,
        );
    }

    public function approve(string $approverId, CarbonImmutable $at, ?string $note = null): void
    {
        $this->assertOpen();

        if ($approverId === $this->employeeId) {
            throw InvariantViolation::because('You cannot approve your own leave application.');
        }

        $this->status = LeaveStatus::Approved;
        $this->decidedBy = $approverId;
        $this->decidedAt = $at;
        $this->decisionNote = $note;

        $this->recordThat(new LeaveApplicationApproved(
            applicationId: $this->id,
            employeeId: $this->employeeId,
            approverId: $approverId,
            workingDays: $this->workingDays,
            occurredAt: $at,
        ));
    }

    public function reject(string $approverId, CarbonImmutable $at, string $reason): void
    {
        $this->assertOpen();

        if (trim($reason) === '') {
            throw InvariantViolation::because(
                'Give a reason when declining leave — the employee needs to know why.'
            );
        }

        $this->status = LeaveStatus::Rejected;
        $this->decidedBy = $approverId;
        $this->decidedAt = $at;
        $this->decisionNote = trim($reason);

        $this->recordThat(new LeaveApplicationRejected(
            applicationId: $this->id,
            employeeId: $this->employeeId,
            workingDays: $this->workingDays,
            occurredAt: $at,
        ));
    }

    /**
     * Withdrawing a pending request, or cancelling approved leave that has not
     * started yet. Once leave has begun, HR has to unwind it manually — the
     * payroll figures for that period may already be in flight.
     */
    public function cancel(string $cancelledBy, CarbonImmutable $at, bool $allowAfterStart = false): void
    {
        if ($this->status->isFinal()) {
            throw LeaveApplicationNotOpen::inStatus($this->status);
        }

        if (! $allowAfterStart && $this->dates->start->lessThanOrEqualTo($at->startOfDay())) {
            throw InvariantViolation::because(
                'This leave has already started. Ask HR to cancel it for you.'
            );
        }

        $wasApproved = $this->status === LeaveStatus::Approved;

        $this->status = LeaveStatus::Cancelled;
        $this->decidedBy = $cancelledBy;
        $this->decidedAt = $at;

        $this->recordThat(new LeaveApplicationCancelled(
            applicationId: $this->id,
            employeeId: $this->employeeId,
            workingDays: $this->workingDays,
            wasApproved: $wasApproved,
            occurredAt: $at,
        ));
    }

    public function attachDocument(string $path): void
    {
        $this->attachmentPath = $path;
    }

    public function overlaps(self $other): bool
    {
        return $this->dates->overlaps($other->dates);
    }

    public function dates(): DateRange
    {
        return $this->dates;
    }

    /** @return list<LeaveDay> */
    public function days(): array
    {
        return $this->days;
    }

    public function workingDays(): float
    {
        return $this->workingDays;
    }

    public function startPortion(): DayPortion
    {
        return $this->days[0]->portion;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function attachmentPath(): ?string
    {
        return $this->attachmentPath;
    }

    public function contactWhileAway(): ?string
    {
        return $this->contactWhileAway;
    }

    public function status(): LeaveStatus
    {
        return $this->status;
    }

    public function appliedAt(): CarbonImmutable
    {
        return $this->appliedAt;
    }

    public function decidedBy(): ?string
    {
        return $this->decidedBy;
    }

    public function decidedAt(): ?CarbonImmutable
    {
        return $this->decidedAt;
    }

    public function decisionNote(): ?string
    {
        return $this->decisionNote;
    }

    private function assertOpen(): void
    {
        if (! $this->status->isOpen()) {
            throw LeaveApplicationNotOpen::inStatus($this->status);
        }
    }
}
