<?php

declare(strict_types=1);

namespace HR\Leave\Application\Command;

use Carbon\CarbonImmutable;
use HR\Employee\Domain\Entity\Employee;
use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Leave\Domain\Entity\LeaveEntitlement;
use HR\Leave\Domain\Entity\LeaveType;
use HR\Leave\Domain\Repository\LeaveEntitlementRepository;
use HR\Leave\Domain\Repository\LeaveTypeRepository;
use HR\Shared\Application\TransactionManager;

/**
 * Opens a leave year.
 *
 * Two things happen per employee and leave type: this year's entitlement is
 * granted, pro-rated for anyone who joined mid-year, and whatever they did not
 * use last year is carried forward up to the type's cap.
 */
final readonly class GrantAnnualEntitlementsHandler
{
    public function __construct(
        private EmployeeRepository $employees,
        private LeaveTypeRepository $leaveTypes,
        private LeaveEntitlementRepository $entitlements,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(GrantAnnualEntitlements $command): int
    {
        $yearStart = CarbonImmutable::create($command->year, 1, 1)->startOfDay();
        $yearEnd = $yearStart->endOfYear()->startOfDay();

        $employees = $command->employeeId !== null
            ? [$this->employees->get(EmployeeId::fromString($command->employeeId))]
            : $this->employees->activeDuring(
                $yearStart->toDateTimeImmutable(),
                $yearEnd->toDateTimeImmutable(),
            );

        $types = array_filter(
            $this->leaveTypes->allActive(),
            static fn (LeaveType $type) => $type->tracksBalance(),
        );

        $granted = 0;

        foreach ($employees as $employee) {
            foreach ($types as $type) {
                if ($this->entitlements->findFor($employee->id->value, $type->id, $command->year) !== null) {
                    continue;
                }

                $previous = $this->entitlements->findFor(
                    $employee->id->value, $type->id, $command->year - 1,
                );

                $this->transaction->transactional(function () use (
                    $employee, $type, $command, $yearStart, $previous
                ): void {
                    $this->entitlements->save(LeaveEntitlement::grant(
                        employeeId: $employee->id->value,
                        leaveTypeId: $type->id,
                        year: $command->year,
                        entitledDays: $this->proRatedDays($type, $employee, $command->year),
                        carriedForwardDays: $previous !== null
                            ? $type->carryForwardFrom($previous->unusedDays())
                            : 0.0,
                        carryForwardExpiresOn: $type->carryForwardExpiryMonths() > 0
                            ? $yearStart->addMonths($type->carryForwardExpiryMonths())->toDateString()
                            : null,
                    ));
                });

                $granted++;
            }
        }

        return $granted;
    }

    /** Someone who joins in September does not get a full year of annual leave. */
    private function proRatedDays(LeaveType $type, Employee $employee, int $year): float
    {
        $full = $type->defaultEntitlementDays();
        $joined = $employee->joinedOn();

        if ($joined->year < $year) {
            return $full;
        }

        if ($joined->year > $year) {
            return 0.0;
        }

        $remainingMonths = 12 - $joined->month + 1;

        // Rounded to the nearest half day — the smallest unit leave is taken in.
        return round($full * $remainingMonths / 12 * 2) / 2;
    }
}
