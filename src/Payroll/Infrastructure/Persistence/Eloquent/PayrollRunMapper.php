<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Persistence\Eloquent;

use Carbon\CarbonImmutable;
use HR\Payroll\Domain\Entity\PayrollRun;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Payroll\Domain\ValueObject\PayrollRunStatus;
use HR\Shared\Domain\ValueObject\Money;

final class PayrollRunMapper
{
    public function toDomain(PayrollRunRecord $record): PayrollRun
    {
        return PayrollRun::reconstitute(
            id: PayrollRunId::fromString($record->id),
            period: PayPeriod::fromString($record->period),
            status: PayrollRunStatus::from($record->status),
            paymentDate: CarbonImmutable::parse($record->payment_date),
            openedBy: $record->opened_by,
            finalisedAt: $record->finalised_at ? CarbonImmutable::parse($record->finalised_at) : null,
            finalisedBy: $record->finalised_by,
            paidAt: $record->paid_at ? CarbonImmutable::parse($record->paid_at) : null,
            payslipCount: (int) $record->payslip_count,
            totalNetPay: Money::of((int) $record->total_net_pay_minor, $record->currency),
            totalEmployerCost: Money::of((int) $record->total_employer_cost_minor, $record->currency),
            notes: $record->notes,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(PayrollRun $run): array
    {
        return [
            'id' => $run->id->value,
            'period' => (string) $run->period,
            'status' => $run->status()->value,
            'payment_date' => $run->paymentDate()->toDateString(),
            'opened_by' => $run->openedBy(),
            'finalised_at' => $run->finalisedAt()?->toDateTimeString(),
            'finalised_by' => $run->finalisedBy(),
            'paid_at' => $run->paidAt()?->toDateTimeString(),
            'payslip_count' => $run->payslipCount(),
            'currency' => $run->totalNetPay()->currency,
            'total_net_pay_minor' => $run->totalNetPay()->minorUnits,
            'total_employer_cost_minor' => $run->totalEmployerCost()->minorUnits,
            'notes' => $run->notes(),
        ];
    }
}
