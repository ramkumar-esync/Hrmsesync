<?php

declare(strict_types=1);

namespace HR\Leave\Application\Command;

use HR\Leave\Domain\Entity\LeaveApplication;
use HR\Leave\Domain\Exception\NoEntitlementGranted;
use HR\Leave\Domain\Exception\OverlappingLeave;
use HR\Leave\Domain\Repository\LeaveApplicationRepository;
use HR\Leave\Domain\Repository\LeaveEntitlementRepository;
use HR\Leave\Domain\Repository\LeaveTypeRepository;
use HR\Leave\Domain\Service\WorkingDayCalculator;
use HR\Leave\Domain\ValueObject\DayPortion;
use HR\Leave\Domain\ValueObject\LeaveTypeId;
use HR\Shared\Application\EventPublisher;
use HR\Shared\Application\TransactionManager;
use HR\Shared\Domain\Contract\Clock;
use HR\Shared\Domain\Exception\InvariantViolation;
use HR\Shared\Domain\ValueObject\DateRange;

/**
 * The employee-facing "apply for leave" use case.
 *
 * Rules are checked in the order an employee would hit them, so the first
 * message they see is the most useful one. The whole thing runs in a
 * transaction with a locked entitlement row: without the lock, two requests
 * submitted at the same moment can each see the last remaining day.
 */
final readonly class ApplyForLeaveHandler
{
    public function __construct(
        private LeaveTypeRepository $leaveTypes,
        private LeaveEntitlementRepository $entitlements,
        private LeaveApplicationRepository $applications,
        private WorkingDayCalculator $workingDays,
        private TransactionManager $transaction,
        private EventPublisher $events,
        private Clock $clock,
        private int $backdateGraceDays,
    ) {}

    public function __invoke(ApplyForLeave $command): LeaveApplication
    {
        $dates = DateRange::of($command->startDate, $command->endDate);
        $startPortion = DayPortion::from($command->startPortion);
        $endPortion = DayPortion::from($command->endPortion);

        $leaveType = $this->leaveTypes->get(LeaveTypeId::fromString($command->leaveTypeId));

        if (! $leaveType->isActive()) {
            throw InvariantViolation::because(
                "{$leaveType->name()} is no longer available. Choose another leave type."
            );
        }

        $this->assertPortionsAllowed($leaveType, $dates, $startPortion, $endPortion);
        $this->assertTimingAllowed($leaveType, $dates);

        if ($leaveType->requiresAttachment() && $command->attachmentPath === null) {
            throw InvariantViolation::because(
                "{$leaveType->name()} needs a supporting document, such as a medical certificate."
            );
        }

        return $this->transaction->transactional(function () use (
            $command, $dates, $startPortion, $endPortion, $leaveType
        ): LeaveApplication {
            $clashes = $this->applications->activeOverlapping($command->employeeId, $dates);

            if ($clashes !== []) {
                $clash = $clashes[0]->dates();
                throw OverlappingLeave::with(
                    $clash->start->toFormattedDateString().' – '.$clash->end->toFormattedDateString()
                );
            }

            $days = $this->workingDays->expand($dates, $startPortion, $endPortion);

            $application = LeaveApplication::submit(
                employeeId: $command->employeeId,
                leaveTypeId: $leaveType->id,
                dates: $dates,
                days: $days,
                reason: $command->reason,
                appliedAt: $this->clock->now(),
                attachmentPath: $command->attachmentPath,
                contactWhileAway: $command->contactWhileAway,
            );

            if ($leaveType->maxConsecutiveDays() !== null
                && $application->workingDays() > $leaveType->maxConsecutiveDays()) {
                throw InvariantViolation::because(sprintf(
                    '%s is limited to %d consecutive days per application.',
                    $leaveType->name(),
                    $leaveType->maxConsecutiveDays(),
                ));
            }

            if ($leaveType->tracksBalance()) {
                $entitlement = $this->entitlements->findForUpdate(
                    $command->employeeId,
                    $leaveType->id,
                    $application->entitlementYear,
                ) ?? throw NoEntitlementGranted::forYear(
                    $leaveType->name(),
                    $application->entitlementYear,
                );

                $entitlement->reserve($application->workingDays());
                $this->entitlements->save($entitlement);
            }

            $this->applications->save($application);
            $this->events->publishAll($application->releaseEvents());

            return $application;
        });
    }

    private function assertPortionsAllowed(
        \HR\Leave\Domain\Entity\LeaveType $leaveType,
        DateRange $dates,
        DayPortion $startPortion,
        DayPortion $endPortion,
    ): void {
        if (! $leaveType->allowsHalfDay() && ($startPortion->isHalf() || $endPortion->isHalf())) {
            throw InvariantViolation::because(
                "{$leaveType->name()} must be taken in whole days."
            );
        }

        if ($dates->isSingleDay() && $startPortion !== $endPortion) {
            throw InvariantViolation::because(
                'For a single day, the start and end portion must match.'
            );
        }
    }

    private function assertTimingAllowed(
        \HR\Leave\Domain\Entity\LeaveType $leaveType,
        DateRange $dates,
    ): void {
        $today = $this->clock->today();

        if ($dates->start->lessThan($today)) {
            $daysLate = (int) $dates->start->diffInDays($today);

            if (! $leaveType->allowsBackdating() || $daysLate > $this->backdateGraceDays) {
                throw InvariantViolation::because(sprintf(
                    'Leave can only be backdated by up to %d days. Ask HR to record this for you.',
                    $this->backdateGraceDays,
                ));
            }
        }

        $notice = $leaveType->minNoticeDays();

        if ($notice > 0 && $dates->start->greaterThanOrEqualTo($today)) {
            $given = (int) $today->diffInDays($dates->start);

            if ($given < $notice) {
                throw InvariantViolation::because(sprintf(
                    '%s needs at least %d day%s notice.',
                    $leaveType->name(),
                    $notice,
                    $notice === 1 ? '' : 's',
                ));
            }
        }
    }
}
