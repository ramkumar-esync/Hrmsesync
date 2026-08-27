<?php

declare(strict_types=1);

namespace HR\Payroll\Presentation\Http\Resource;

use HR\Payroll\Domain\Entity\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PayrollRunResource extends JsonResource
{
    public function __construct(private readonly PayrollRun $run)
    {
        parent::__construct($run);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $run = $this->run;

        return [
            'id' => $run->id->value,
            'period' => (string) $run->period,
            'period_label' => $run->period->label(),
            'status' => $run->status()->value,
            'status_label' => $run->status()->label(),
            'editable' => $run->status()->isEditable(),
            'payment_date' => $run->paymentDate()->toDateString(),
            'payslip_count' => $run->payslipCount(),
            'totals' => [
                'net_pay' => $run->totalNetPay(),
                'employer_cost' => $run->totalEmployerCost(),
            ],
            'finalised_at' => $run->finalisedAt()?->toIso8601String(),
            'paid_at' => $run->paidAt()?->toIso8601String(),
            'notes' => $run->notes(),
        ];
    }
}
