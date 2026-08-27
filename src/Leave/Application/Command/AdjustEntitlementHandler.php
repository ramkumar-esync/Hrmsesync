<?php

declare(strict_types=1);

namespace HR\Leave\Application\Command;

use HR\Leave\Domain\Entity\LeaveEntitlement;
use HR\Leave\Domain\Exception\NoEntitlementGranted;
use HR\Leave\Domain\Repository\LeaveEntitlementRepository;
use HR\Leave\Domain\Repository\LeaveTypeRepository;
use HR\Leave\Domain\ValueObject\LeaveTypeId;
use HR\Shared\Application\TransactionManager;
use Illuminate\Support\Facades\Log;

final readonly class AdjustEntitlementHandler
{
    public function __construct(
        private LeaveEntitlementRepository $entitlements,
        private LeaveTypeRepository $leaveTypes,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(AdjustEntitlement $command): LeaveEntitlement
    {
        return $this->transaction->transactional(function () use ($command): LeaveEntitlement {
            $leaveTypeId = LeaveTypeId::fromString($command->leaveTypeId);
            $leaveType = $this->leaveTypes->get($leaveTypeId);

            $entitlement = $this->entitlements->findForUpdate(
                $command->employeeId, $leaveTypeId, $command->year,
            ) ?? throw NoEntitlementGranted::forYear($leaveType->name(), $command->year);

            $entitlement->adjustBy($command->days);
            $this->entitlements->save($entitlement);

            // Balance changes are the kind of thing employees dispute later.
            Log::channel('audit')->info('Leave entitlement adjusted', [
                'employee_id' => $command->employeeId,
                'leave_type' => $leaveType->code(),
                'year' => $command->year,
                'days' => $command->days,
                'reason' => $command->reason,
            ]);

            return $entitlement;
        });
    }
}
