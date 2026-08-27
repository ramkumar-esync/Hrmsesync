<?php

declare(strict_types=1);

namespace HR\Payroll\Presentation\Http\Resource;

use HR\Payroll\Domain\Entity\Payslip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PayslipResource extends JsonResource
{
    public function __construct(private readonly Payslip $payslip)
    {
        parent::__construct($payslip);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $payslip = $this->payslip;

        return [
            'id' => $payslip->id->value,
            'payroll_run_id' => $payslip->runId->value,
            'period' => (string) $payslip->period,
            'period_label' => $payslip->period->label(),
            'status' => $payslip->status()->value,
            'employee' => $payslip->employee(),
            'earnings' => $payslip->earnings(),
            'deductions' => $payslip->deductions(),
            'employer_contributions' => $payslip->employerContributions(),
            'totals' => [
                'gross_pay' => $payslip->grossPay(),
                'total_deductions' => $payslip->totalDeductions(),
                'net_pay' => $payslip->netPay(),
                'employer_cost' => $payslip->employerCost(),
            ],
            'issued_at' => $payslip->issuedAt()?->toIso8601String(),
            'document_ready' => $payslip->documentPath() !== null,
            'remarks' => $payslip->remarks(),
        ];
    }
}
