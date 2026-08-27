<?php

declare(strict_types=1);

namespace Tests\Unit;

use HR\Leave\Domain\Entity\LeaveEntitlement;
use HR\Leave\Domain\Exception\InsufficientLeaveBalance;
use HR\Leave\Domain\ValueObject\LeaveTypeId;
use HR\Shared\Domain\Exception\InvariantViolation;
use PHPUnit\Framework\TestCase;

/**
 * The reservation cycle is the heart of the leave domain: days are held while
 * an application is pending, then either consumed or handed back.
 */
final class LeaveEntitlementTest extends TestCase
{
    private function entitlement(float $days = 14.0): LeaveEntitlement
    {
        return LeaveEntitlement::grant(
            employeeId: '018f0000-0000-7000-8000-000000000001',
            leaveTypeId: LeaveTypeId::generate(),
            year: 2026,
            entitledDays: $days,
        );
    }

    public function test_pending_days_reduce_the_available_balance_immediately(): void
    {
        $entitlement = $this->entitlement();
        $entitlement->reserve(3.0);

        $balance = $entitlement->balance();

        $this->assertSame(14.0, $balance->granted());
        $this->assertSame(3.0, $balance->pending);
        $this->assertSame(0.0, $balance->taken);
        $this->assertSame(11.0, $balance->available());
    }

    public function test_approval_converts_a_reservation_into_days_taken(): void
    {
        $entitlement = $this->entitlement();
        $entitlement->reserve(3.0);
        $entitlement->consumeReservation(3.0);

        $balance = $entitlement->balance();

        $this->assertSame(0.0, $balance->pending);
        $this->assertSame(3.0, $balance->taken);
        $this->assertSame(11.0, $balance->available());
    }

    public function test_rejection_returns_the_reserved_days(): void
    {
        $entitlement = $this->entitlement();
        $entitlement->reserve(5.0);
        $entitlement->releaseReservation(5.0);

        $this->assertSame(14.0, $entitlement->balance()->available());
    }

    public function test_cancelling_approved_leave_restores_the_days(): void
    {
        $entitlement = $this->entitlement();
        $entitlement->reserve(2.0);
        $entitlement->consumeReservation(2.0);
        $entitlement->restore(2.0);

        $this->assertSame(14.0, $entitlement->balance()->available());
    }

    public function test_it_refuses_to_reserve_more_than_is_available(): void
    {
        $entitlement = $this->entitlement(5.0);

        $this->expectException(InsufficientLeaveBalance::class);

        $entitlement->reserve(5.5);
    }

    public function test_two_reservations_cannot_exceed_the_balance_between_them(): void
    {
        $entitlement = $this->entitlement(5.0);
        $entitlement->reserve(3.0);

        $this->expectException(InsufficientLeaveBalance::class);

        // Only 2 days remain once the first request is pending.
        $entitlement->reserve(2.5);
    }

    public function test_half_days_do_not_accumulate_float_error(): void
    {
        $entitlement = $this->entitlement(3.0);

        for ($i = 0; $i < 6; $i++) {
            $entitlement->reserve(0.5);
        }

        $this->assertSame(0.0, $entitlement->balance()->available());
    }

    public function test_carried_forward_days_are_spendable(): void
    {
        $entitlement = LeaveEntitlement::grant(
            employeeId: '018f0000-0000-7000-8000-000000000001',
            leaveTypeId: LeaveTypeId::generate(),
            year: 2026,
            entitledDays: 14.0,
            carriedForwardDays: 5.0,
        );

        $this->assertSame(19.0, $entitlement->balance()->available());
    }

    public function test_an_adjustment_cannot_drop_the_balance_below_days_already_used(): void
    {
        $entitlement = $this->entitlement(10.0);
        $entitlement->reserve(8.0);
        $entitlement->consumeReservation(8.0);

        $this->expectException(InvariantViolation::class);

        $entitlement->adjustBy(-5.0);
    }
}
