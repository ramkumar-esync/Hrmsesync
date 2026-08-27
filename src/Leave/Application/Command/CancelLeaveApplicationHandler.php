<?php

declare(strict_types=1);

namespace HR\Leave\Application\Command;

use HR\Leave\Domain\Entity\LeaveApplication;
use HR\Leave\Domain\Repository\LeaveApplicationRepository;
use HR\Leave\Domain\Repository\LeaveEntitlementRepository;
use HR\Leave\Domain\Repository\LeaveTypeRepository;
use HR\Leave\Domain\ValueObject\LeaveApplicationId;
use HR\Leave\Domain\ValueObject\LeaveStatus;
use HR\Shared\Application\EventPublisher;
use HR\Shared\Application\TransactionManager;
use HR\Shared\Domain\Contract\Clock;

final readonly class CancelLeaveApplicationHandler
{
    public function __construct(
        private LeaveApplicationRepository $applications,
        private LeaveEntitlementRepository $entitlements,
        private LeaveTypeRepository $leaveTypes,
        private TransactionManager $transaction,
        private EventPublisher $events,
        private Clock $clock,
    ) {}

    public function __invoke(CancelLeaveApplication $command): LeaveApplication
    {
        return $this->transaction->transactional(function () use ($command): LeaveApplication {
            $application = $this->applications->get(
                LeaveApplicationId::fromString($command->applicationId)
            );

            $wasApproved = $application->status() === LeaveStatus::Approved;

            $application->cancel($command->cancelledBy, $this->clock->now(), $command->allowAfterStart);

            $leaveType = $this->leaveTypes->get($application->leaveTypeId);

            if ($leaveType->tracksBalance()) {
                $entitlement = $this->entitlements->findForUpdate(
                    $application->employeeId,
                    $application->leaveTypeId,
                    $application->entitlementYear,
                );

                if ($entitlement !== null) {
                    $wasApproved
                        ? $entitlement->restore($application->workingDays())
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
