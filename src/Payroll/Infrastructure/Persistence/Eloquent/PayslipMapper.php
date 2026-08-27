<?php

declare(strict_types=1);

namespace HR\Payroll\Infrastructure\Persistence\Eloquent;

use Carbon\CarbonImmutable;
use HR\Payroll\Domain\Entity\Payslip;
use HR\Payroll\Domain\ValueObject\EmployeeSnapshot;
use HR\Payroll\Domain\ValueObject\EmployerContribution;
use HR\Payroll\Domain\ValueObject\PayComponent;
use HR\Payroll\Domain\ValueObject\PayComponentType;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Payroll\Domain\ValueObject\PayslipId;
use HR\Payroll\Domain\ValueObject\PayslipStatus;
use HR\Shared\Domain\ValueObject\Money;

/**
 * Payslip lines are stored as JSON rather than child tables.
 *
 * They are only ever read as a complete document, they are frozen once issued,
 * and their shape is a snapshot of a moment in time — so a normalised schema
 * would buy nothing and cost a join per payslip.
 */
final class PayslipMapper
{
    public function toDomain(PayslipRecord $record): Payslip
    {
        $currency = $record->currency;

        return Payslip::reconstitute(
            id: PayslipId::fromString($record->id),
            runId: PayrollRunId::fromString($record->payroll_run_id),
            period: PayPeriod::fromString($record->period),
            employee: EmployeeSnapshot::fromArray($record->employee_snapshot),
            earnings: $this->componentsFromArray($record->earnings ?? [], $currency),
            deductions: $this->componentsFromArray($record->deductions ?? [], $currency),
            employerContributions: array_map(
                static fn (array $line) => new EmployerContribution(
                    PayComponentType::from($line['type']),
                    Money::of((int) $line['minor_units'], $currency),
                    $line['description'] ?? null,
                ),
                $record->employer_contributions ?? [],
            ),
            status: PayslipStatus::from($record->status),
            issuedAt: $record->issued_at ? CarbonImmutable::parse($record->issued_at) : null,
            documentPath: $record->document_path,
            supersedes: $record->supersedes_payslip_id
                ? PayslipId::fromString($record->supersedes_payslip_id)
                : null,
            remarks: $record->remarks,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(Payslip $payslip): array
    {
        return [
            'id' => $payslip->id->value,
            'payroll_run_id' => $payslip->runId->value,
            'employee_id' => $payslip->employee()->employeeId,
            'period' => (string) $payslip->period,
            'currency' => $payslip->currency(),
            'employee_snapshot' => $payslip->employee()->jsonSerialize(),
            'earnings' => $this->componentsToArray($payslip->earnings()),
            'deductions' => $this->componentsToArray($payslip->deductions()),
            'employer_contributions' => array_map(
                static fn (EmployerContribution $line) => [
                    'type' => $line->type->value,
                    'minor_units' => $line->amount->minorUnits,
                    'description' => $line->description,
                ],
                $payslip->employerContributions(),
            ),
            // Totals are denormalised so reports and dashboards never have to
            // rehydrate and re-add every payslip.
            'gross_pay_minor' => $payslip->grossPay()->minorUnits,
            'total_deductions_minor' => $payslip->totalDeductions()->minorUnits,
            'net_pay_minor' => $payslip->netPay()->minorUnits,
            'employer_cost_minor' => $payslip->employerCost()->minorUnits,
            'status' => $payslip->status()->value,
            'issued_at' => $payslip->issuedAt()?->toDateTimeString(),
            'document_path' => $payslip->documentPath(),
            'supersedes_payslip_id' => $payslip->supersedes()?->value,
            'remarks' => $payslip->remarks(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return list<PayComponent>
     */
    private function componentsFromArray(array $lines, string $currency): array
    {
        return array_map(
            static fn (array $line) => new PayComponent(
                PayComponentType::from($line['type']),
                Money::of((int) $line['minor_units'], $currency),
                $line['description'] ?? null,
                (bool) ($line['system_generated'] ?? false),
            ),
            $lines,
        );
    }

    /**
     * @param  list<PayComponent>  $components
     * @return list<array<string, mixed>>
     */
    private function componentsToArray(array $components): array
    {
        return array_map(
            static fn (PayComponent $line) => [
                'type' => $line->type->value,
                'minor_units' => $line->amount->minorUnits,
                'description' => $line->description,
                'system_generated' => $line->systemGenerated,
            ],
            $components,
        );
    }
}
