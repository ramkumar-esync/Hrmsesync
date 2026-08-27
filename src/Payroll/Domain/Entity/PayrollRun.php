<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Entity;

use Carbon\CarbonImmutable;
use HR\Payroll\Domain\Event\PayrollRunFinalised;
use HR\Payroll\Domain\Exception\PayrollRunLocked;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Payroll\Domain\ValueObject\PayrollRunStatus;
use HR\Shared\Domain\Event\RecordsDomainEvents;
use HR\Shared\Domain\Exception\InvariantViolation;
use HR\Shared\Domain\ValueObject\Money;

/**
 * A payroll cycle for one period.
 *
 * The run owns the lifecycle (draft → finalised → paid); individual payslips
 * are their own aggregate so a run of 5,000 staff never has to be loaded whole.
 */
final class PayrollRun
{
    use RecordsDomainEvents;

    private function __construct(
        public readonly PayrollRunId $id,
        public readonly PayPeriod $period,
        private PayrollRunStatus $status,
        private CarbonImmutable $paymentDate,
        private readonly string $openedBy,
        private ?CarbonImmutable $finalisedAt,
        private ?string $finalisedBy,
        private ?CarbonImmutable $paidAt,
        private int $payslipCount,
        private Money $totalNetPay,
        private Money $totalEmployerCost,
        private ?string $notes,
    ) {}

    public static function open(
        PayPeriod $period,
        CarbonImmutable $paymentDate,
        string $openedBy,
        ?string $notes = null,
        ?PayrollRunId $id = null,
    ): self {
        if ($paymentDate->lessThan($period->startDate())) {
            throw InvariantViolation::because(
                'The payment date cannot fall before the pay period begins.'
            );
        }

        return new self(
            id: $id ?? PayrollRunId::generate(),
            period: $period,
            status: PayrollRunStatus::Draft,
            paymentDate: $paymentDate->startOfDay(),
            openedBy: $openedBy,
            finalisedAt: null,
            finalisedBy: null,
            paidAt: null,
            payslipCount: 0,
            totalNetPay: Money::zero(),
            totalEmployerCost: Money::zero(),
            notes: $notes,
        );
    }

    public static function reconstitute(
        PayrollRunId $id,
        PayPeriod $period,
        PayrollRunStatus $status,
        CarbonImmutable $paymentDate,
        string $openedBy,
        ?CarbonImmutable $finalisedAt,
        ?string $finalisedBy,
        ?CarbonImmutable $paidAt,
        int $payslipCount,
        Money $totalNetPay,
        Money $totalEmployerCost,
        ?string $notes,
    ): self {
        return new self(
            $id, $period, $status, $paymentDate, $openedBy, $finalisedAt, $finalisedBy,
            $paidAt, $payslipCount, $totalNetPay, $totalEmployerCost, $notes,
        );
    }

    public function assertAcceptsChanges(): void
    {
        if (! $this->status->isEditable()) {
            throw PayrollRunLocked::inState($this->status);
        }
    }

    public function reschedulePayment(CarbonImmutable $paymentDate): void
    {
        $this->assertAcceptsChanges();

        $this->paymentDate = $paymentDate->startOfDay();
    }

    /**
     * Close the run. Totals are passed in by the application layer after every
     * payslip has been issued, so they are stored rather than recomputed.
     */
    public function finalise(
        int $payslipCount,
        Money $totalNetPay,
        Money $totalEmployerCost,
        string $finalisedBy,
        CarbonImmutable $finalisedAt,
    ): void {
        $this->assertAcceptsChanges();

        if ($payslipCount < 1) {
            throw InvariantViolation::because(
                'A payroll run needs at least one payslip before it can be finalised.'
            );
        }

        $this->status = PayrollRunStatus::Finalised;
        $this->payslipCount = $payslipCount;
        $this->totalNetPay = $totalNetPay;
        $this->totalEmployerCost = $totalEmployerCost;
        $this->finalisedBy = $finalisedBy;
        $this->finalisedAt = $finalisedAt;

        $this->recordThat(new PayrollRunFinalised(
            runId: $this->id,
            period: $this->period,
            payslipCount: $payslipCount,
            totalNetPay: $totalNetPay,
            occurredAt: $finalisedAt,
        ));
    }

    public function markAsPaid(CarbonImmutable $paidAt): void
    {
        if ($this->status !== PayrollRunStatus::Finalised) {
            throw InvariantViolation::because(
                'Only a finalised run can be marked as paid.'
            );
        }

        $this->status = PayrollRunStatus::Paid;
        $this->paidAt = $paidAt;
    }

    public function cancel(): void
    {
        $this->assertAcceptsChanges();

        $this->status = PayrollRunStatus::Cancelled;
    }

    public function status(): PayrollRunStatus
    {
        return $this->status;
    }

    public function paymentDate(): CarbonImmutable
    {
        return $this->paymentDate;
    }

    public function openedBy(): string
    {
        return $this->openedBy;
    }

    public function finalisedAt(): ?CarbonImmutable
    {
        return $this->finalisedAt;
    }

    public function finalisedBy(): ?string
    {
        return $this->finalisedBy;
    }

    public function paidAt(): ?CarbonImmutable
    {
        return $this->paidAt;
    }

    public function payslipCount(): int
    {
        return $this->payslipCount;
    }

    public function totalNetPay(): Money
    {
        return $this->totalNetPay;
    }

    public function totalEmployerCost(): Money
    {
        return $this->totalEmployerCost;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }
}
