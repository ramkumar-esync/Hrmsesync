<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Entity;

use HR\Leave\Domain\Exception\InsufficientLeaveBalance;
use HR\Leave\Domain\ValueObject\LeaveBalance;
use HR\Leave\Domain\ValueObject\LeaveEntitlementId;
use HR\Leave\Domain\ValueObject\LeaveTypeId;
use HR\Shared\Domain\Exception\InvariantViolation;

/**
 * One employee's balance of one leave type for one year.
 *
 * Days move through three states: granted → pending → taken. Applying reserves
 * days immediately so two applications submitted minutes apart cannot both be
 * approved against the same remaining day; the reservation is released if the
 * application is rejected or withdrawn.
 */
final class LeaveEntitlement
{
    private function __construct(
        public readonly LeaveEntitlementId $id,
        public readonly string $employeeId,
        public readonly LeaveTypeId $leaveTypeId,
        public readonly int $year,
        private float $entitledDays,
        private float $carriedForwardDays,
        private float $adjustmentDays,
        private float $takenDays,
        private float $pendingDays,
        private ?string $carryForwardExpiresOn,
    ) {}

    public static function grant(
        string $employeeId,
        LeaveTypeId $leaveTypeId,
        int $year,
        float $entitledDays,
        float $carriedForwardDays = 0.0,
        ?string $carryForwardExpiresOn = null,
        ?LeaveEntitlementId $id = null,
    ): self {
        if ($entitledDays < 0 || $carriedForwardDays < 0) {
            throw InvariantViolation::because('Granted leave days cannot be negative.');
        }

        return new self(
            id: $id ?? LeaveEntitlementId::generate(),
            employeeId: $employeeId,
            leaveTypeId: $leaveTypeId,
            year: $year,
            entitledDays: $entitledDays,
            carriedForwardDays: $carriedForwardDays,
            adjustmentDays: 0.0,
            takenDays: 0.0,
            pendingDays: 0.0,
            carryForwardExpiresOn: $carryForwardExpiresOn,
        );
    }

    public static function reconstitute(
        LeaveEntitlementId $id,
        string $employeeId,
        LeaveTypeId $leaveTypeId,
        int $year,
        float $entitledDays,
        float $carriedForwardDays,
        float $adjustmentDays,
        float $takenDays,
        float $pendingDays,
        ?string $carryForwardExpiresOn,
    ): self {
        return new self(
            $id, $employeeId, $leaveTypeId, $year, $entitledDays, $carriedForwardDays,
            $adjustmentDays, $takenDays, $pendingDays, $carryForwardExpiresOn,
        );
    }

    /** Hold days while an application waits for a decision. */
    public function reserve(float $days): void
    {
        $this->assertPositive($days);

        if (! $this->balance()->covers($days)) {
            throw InsufficientLeaveBalance::needing($days, $this->balance()->available());
        }

        $this->pendingDays = round($this->pendingDays + $days, 2);
    }

    /** Give reserved days back — the application was rejected or withdrawn. */
    public function releaseReservation(float $days): void
    {
        $this->assertPositive($days);

        $this->pendingDays = round(max($this->pendingDays - $days, 0.0), 2);
    }

    /** Approval: the reservation becomes leave actually taken. */
    public function consumeReservation(float $days): void
    {
        $this->assertPositive($days);

        if ($this->pendingDays + 0.001 < $days) {
            throw InvariantViolation::because(
                'Cannot approve more days than were reserved on this entitlement.'
            );
        }

        $this->pendingDays = round($this->pendingDays - $days, 2);
        $this->takenDays = round($this->takenDays + $days, 2);
    }

    /** Cancelling leave that was already approved. */
    public function restore(float $days): void
    {
        $this->assertPositive($days);

        $this->takenDays = round(max($this->takenDays - $days, 0.0), 2);
    }

    /** A manual correction by HR, positive or negative, with a reason recorded elsewhere. */
    public function adjustBy(float $days): void
    {
        $adjusted = round($this->adjustmentDays + $days, 2);

        if ($this->entitledDays + $this->carriedForwardDays + $adjusted < $this->takenDays) {
            throw InvariantViolation::because(
                'That adjustment would take the balance below the days already used.'
            );
        }

        $this->adjustmentDays = $adjusted;
    }

    public function topUpEntitlement(float $days): void
    {
        $this->assertPositive($days);

        $this->entitledDays = round($this->entitledDays + $days, 2);
    }

    public function expireCarryForward(): void
    {
        $this->carriedForwardDays = 0.0;
        $this->carryForwardExpiresOn = null;
    }

    public function balance(): LeaveBalance
    {
        return new LeaveBalance(
            entitled: $this->entitledDays,
            carriedForward: $this->carriedForwardDays,
            adjustment: $this->adjustmentDays,
            taken: $this->takenDays,
            pending: $this->pendingDays,
        );
    }

    public function unusedDays(): float
    {
        return $this->balance()->available();
    }

    public function carryForwardExpiresOn(): ?string
    {
        return $this->carryForwardExpiresOn;
    }

    private function assertPositive(float $days): void
    {
        if ($days <= 0) {
            throw InvariantViolation::because('Leave day movements must be greater than zero.');
        }
    }
}
