<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Entity;

use Carbon\CarbonImmutable;
use HR\Payroll\Domain\Event\PayslipIssued;
use HR\Payroll\Domain\Exception\PayslipLocked;
use HR\Payroll\Domain\ValueObject\EmployeeSnapshot;
use HR\Payroll\Domain\ValueObject\EmployerContribution;
use HR\Payroll\Domain\ValueObject\PayComponent;
use HR\Payroll\Domain\ValueObject\PayComponentType;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Payroll\Domain\ValueObject\PayslipId;
use HR\Payroll\Domain\ValueObject\PayslipStatus;
use HR\Shared\Domain\Event\RecordsDomainEvents;
use HR\Shared\Domain\Exception\InvariantViolation;
use HR\Shared\Domain\ValueObject\Money;

/**
 * One employee's pay for one period.
 *
 * Editable while the parent run is a draft. Once issued it is frozen: a
 * correction produces a new payslip that supersedes this one, so the audit
 * trail always shows what the employee was originally given.
 */
final class Payslip
{
    use RecordsDomainEvents;

    /**
     * @param  list<PayComponent>  $earnings
     * @param  list<PayComponent>  $deductions
     * @param  list<EmployerContribution>  $employerContributions
     */
    private function __construct(
        public readonly PayslipId $id,
        public readonly PayrollRunId $runId,
        public readonly PayPeriod $period,
        private EmployeeSnapshot $employee,
        private array $earnings,
        private array $deductions,
        private array $employerContributions,
        private PayslipStatus $status,
        private ?CarbonImmutable $issuedAt,
        private ?string $documentPath,
        private ?PayslipId $supersedes,
        private ?string $remarks,
    ) {}

    /** @param list<PayComponent> $earnings */
    public static function draft(
        PayrollRunId $runId,
        PayPeriod $period,
        EmployeeSnapshot $employee,
        array $earnings = [],
        ?PayslipId $id = null,
    ): self {
        return new self(
            id: $id ?? PayslipId::generate(),
            runId: $runId,
            period: $period,
            employee: $employee,
            earnings: array_values($earnings),
            deductions: [],
            employerContributions: [],
            status: PayslipStatus::Draft,
            issuedAt: null,
            documentPath: null,
            supersedes: null,
            remarks: null,
        );
    }

    /**
     * @param  list<PayComponent>  $earnings
     * @param  list<PayComponent>  $deductions
     * @param  list<EmployerContribution>  $employerContributions
     */
    public static function reconstitute(
        PayslipId $id,
        PayrollRunId $runId,
        PayPeriod $period,
        EmployeeSnapshot $employee,
        array $earnings,
        array $deductions,
        array $employerContributions,
        PayslipStatus $status,
        ?CarbonImmutable $issuedAt,
        ?string $documentPath,
        ?PayslipId $supersedes,
        ?string $remarks,
    ): self {
        return new self(
            $id, $runId, $period, $employee, $earnings, $deductions,
            $employerContributions, $status, $issuedAt, $documentPath, $supersedes, $remarks,
        );
    }

    public function addEarning(PayComponent $component): void
    {
        $this->assertEditable();

        if (! $component->type->isEarning()) {
            throw InvariantViolation::because("{$component->type->value} cannot be added as an earning.");
        }

        $this->earnings[] = $component;
    }

    public function addDeduction(PayComponent $component): void
    {
        $this->assertEditable();

        if ($component->type->isEarning()) {
            throw InvariantViolation::because("{$component->type->value} cannot be added as a deduction.");
        }

        $this->deductions[] = $component;
    }

    public function addEmployerContribution(EmployerContribution $contribution): void
    {
        $this->assertEditable();

        $this->employerContributions[] = $contribution;
    }

    /** Drops every system-calculated line so the payslip can be recalculated cleanly. */
    public function clearCalculatedLines(): void
    {
        $this->assertEditable();

        $this->deductions = array_values(array_filter(
            $this->deductions,
            static fn (PayComponent $line) => ! $line->systemGenerated,
        ));

        $this->employerContributions = [];
    }

    public function replaceEarnings(array $earnings): void
    {
        $this->assertEditable();

        foreach ($earnings as $earning) {
            if (! $earning instanceof PayComponent || ! $earning->type->isEarning()) {
                throw InvariantViolation::because('Only earning components may be set as earnings.');
            }
        }

        $this->earnings = array_values($earnings);
    }

    /** The wage base statutory contributions are calculated on. */
    public function statutoryWages(): Money
    {
        return Money::sum(array_map(
            static fn (PayComponent $line) => $line->amount,
            array_filter(
                $this->earnings,
                static fn (PayComponent $line) => $line->type->countsTowardStatutoryWages(),
            ),
        ), $this->currency());
    }

    public function grossPay(): Money
    {
        return Money::sum(
            array_map(static fn (PayComponent $line) => $line->amount, $this->earnings),
            $this->currency(),
        );
    }

    public function totalDeductions(): Money
    {
        return Money::sum(
            array_map(static fn (PayComponent $line) => $line->amount, $this->deductions),
            $this->currency(),
        );
    }

    public function totalEmployerContributions(): Money
    {
        return Money::sum(
            array_map(static fn (EmployerContribution $line) => $line->amount, $this->employerContributions),
            $this->currency(),
        );
    }

    public function netPay(): Money
    {
        return $this->grossPay()->subtract($this->totalDeductions());
    }

    /** Cost to company: gross pay plus everything the employer contributes. */
    public function employerCost(): Money
    {
        return $this->grossPay()->add($this->totalEmployerContributions());
    }

    public function issue(CarbonImmutable $issuedAt): void
    {
        $this->assertEditable();

        if ($this->netPay()->isNegative()) {
            throw InvariantViolation::because(
                "Payslip for {$this->employee->employeeNumber} has negative net pay ("
                .$this->netPay()->format().'). Review the deductions before finalising.'
            );
        }

        $this->status = PayslipStatus::Issued;
        $this->issuedAt = $issuedAt;

        $this->recordThat(new PayslipIssued(
            payslipId: $this->id,
            runId: $this->runId,
            employeeId: $this->employee->employeeId,
            period: $this->period,
            netPay: $this->netPay(),
            occurredAt: $issuedAt,
        ));
    }

    public function attachDocument(string $path): void
    {
        $this->documentPath = $path;
    }

    public function supersede(): void
    {
        if ($this->status !== PayslipStatus::Issued) {
            throw InvariantViolation::because('Only an issued payslip can be superseded.');
        }

        $this->status = PayslipStatus::Superseded;
    }

    public function markAsCorrectionOf(PayslipId $original): void
    {
        $this->supersedes = $original;
    }

    public function addRemarks(?string $remarks): void
    {
        $this->remarks = $remarks;
    }

    public function currency(): string
    {
        return $this->earnings[0]->amount->currency
            ?? $this->deductions[0]->amount->currency
            ?? Money::zero()->currency;
    }

    public function employee(): EmployeeSnapshot
    {
        return $this->employee;
    }

    /** @return list<PayComponent> */
    public function earnings(): array
    {
        return $this->earnings;
    }

    /** @return list<PayComponent> */
    public function deductions(): array
    {
        return $this->deductions;
    }

    /** @return list<EmployerContribution> */
    public function employerContributions(): array
    {
        return $this->employerContributions;
    }

    public function status(): PayslipStatus
    {
        return $this->status;
    }

    public function issuedAt(): ?CarbonImmutable
    {
        return $this->issuedAt;
    }

    public function documentPath(): ?string
    {
        return $this->documentPath;
    }

    public function supersedes(): ?PayslipId
    {
        return $this->supersedes;
    }

    public function remarks(): ?string
    {
        return $this->remarks;
    }

    public function deductionOfType(PayComponentType $type): ?PayComponent
    {
        foreach ($this->deductions as $deduction) {
            if ($deduction->type === $type) {
                return $deduction;
            }
        }

        return null;
    }

    private function assertEditable(): void
    {
        if (! $this->status->isEditable()) {
            throw PayslipLocked::because(
                'This payslip has already been issued. Issue a correction payslip instead of editing it.'
            );
        }
    }
}
