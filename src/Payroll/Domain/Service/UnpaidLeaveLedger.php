<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Service;

use HR\Payroll\Domain\ValueObject\PayPeriod;

/**
 * Anti-corruption port into the Leave context.
 *
 * Payroll needs one number — how many unpaid days this person took — and must
 * not reach into leave tables or reason about leave statuses to get it.
 */
interface UnpaidLeaveLedger
{
    public function unpaidDaysFor(string $employeeId, PayPeriod $period): float;
}
