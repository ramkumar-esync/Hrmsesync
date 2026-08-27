<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Event;

use Carbon\CarbonImmutable;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Shared\Domain\Event\DomainEvent;
use HR\Shared\Domain\ValueObject\Money;

final readonly class PayrollRunFinalised implements DomainEvent
{
    public function __construct(
        public PayrollRunId $runId,
        public PayPeriod $period,
        public int $payslipCount,
        public Money $totalNetPay,
        private CarbonImmutable $occurredAt,
    ) {}

    public function occurredAt(): CarbonImmutable
    {
        return $this->occurredAt;
    }
}
