<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Event;

use Carbon\CarbonImmutable;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Payroll\Domain\ValueObject\PayslipId;
use HR\Shared\Domain\Event\DomainEvent;
use HR\Shared\Domain\ValueObject\Money;

final readonly class PayslipIssued implements DomainEvent
{
    public function __construct(
        public PayslipId $payslipId,
        public PayrollRunId $runId,
        public string $employeeId,
        public PayPeriod $period,
        public Money $netPay,
        private CarbonImmutable $occurredAt,
    ) {}

    public function occurredAt(): CarbonImmutable
    {
        return $this->occurredAt;
    }
}
