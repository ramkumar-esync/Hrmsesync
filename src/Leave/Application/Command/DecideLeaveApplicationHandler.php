<?php

declare(strict_types=1);

namespace HR\Leave\Application\Command;

use HR\Leave\Domain\Entity\LeaveApplication;
use HR\Leave\Domain\Repository\LeaveApplicationRepository;
use HR\Leave\Domain\Repository\LeaveEntitlementRepository;
use HR\Leave\Domain\Repository\LeaveTypeRepository;
use HR\Leave\Domain\ValueObject\LeaveApplicationId;
use HR\Shared\Application\EventPublisher;
use HR\Shared\Application\TransactionManager;
use HR\Shared\Domain\Contract\Clock;

/**
 * Approve or decline. Both paths move the reserved days: approval turns the
 * reservation into leave taken, rejection hands the days straight back.
 */
final readonly class DecideLeaveApplicationHandler
{
    public function __construct(
        private LeaveApplicationRepository $applications,
        private LeaveEntitlementRepository $entitlements,
        private LeaveTypeRepository $leaveTypes,
        private TransactionManager $transaction,
        private EventPublisher $events,
        private Clock $clock,
    ) {}

    public function __invoke(DecideLeaveApplication $command): LeaveApplication
    {
        return $this->transaction->transactional(function () use ($command): LeaveApplication {
            $application = $this->applications->get(
                LeaveApplicationId::fromString($command->applicationId)
            );

            $now = $this->clock->now();

            if ($command->approve) {
                $application->approve($command->approverEmployeeId, $now, $command->note);
            } else {
                $application->reject($command->approverEmployeeId, $now, (string) $command->note);
            }

            $leaveType = $this->leaveTypes->get($application->leaveTypeId);

            if ($leaveType->tracksBalance()) {
                $entitlement = $this->entitlements->findForUpdate(
                    $application->employeeId,
                    $application->leaveTypeId,
                    $application->entitlementYear,
                );

                if ($entitlement !== null) {
                    $command->approve
                        ? $entitlement->consumeReservation($application->workingDays())
                        : $entitlement->releaseReservation($application->workingDays());

                    $this->entitlements->save($entitlement);
                }
            }

            $this->applications->save($application);
            $this->events->publishAll($application->releaseEvents());

            return $application;
        });
    }
}
