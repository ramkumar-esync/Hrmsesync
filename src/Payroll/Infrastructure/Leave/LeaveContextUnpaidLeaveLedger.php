<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Leave;

use HR\Leave\Application\Query\UnpaidLeaveDaysQuery;
use HR\Payroll\Domain\Service\UnpaidLeaveLedger;
use HR\Payroll\Domain\ValueObject\PayPeriod;

/**
 * Adapter between the two contexts.
 *
 * Payroll depends on its own UnpaidLeaveLedger port; this class is the only
 * place where the two vocabularies meet. If leave ever moves to a separate
 * service, this is the single file that changes.
 */
final readonly class LeaveContextUnpaidLeaveLedger implements UnpaidLeaveLedger
{
    public function __construct(private UnpaidLeaveDaysQuery $query) {}

    public function unpaidDaysFor(string $employeeId, PayPeriod $period): float
    {
        return $this->query->totalFor(
            employeeId: $employeeId,
            from: $period->startDate()->toDateString(),
            to: $period->endDate()->toDateString(),
        );
    }
}
