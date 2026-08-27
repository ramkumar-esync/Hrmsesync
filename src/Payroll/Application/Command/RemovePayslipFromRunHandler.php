<?php

declare(strict_types=1);

namespace HR\Payroll\Application\Command;

use HR\Payroll\Domain\Repository\PayrollRunRepository;
use HR\Payroll\Domain\Repository\PayslipRepository;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Payroll\Domain\ValueObject\PayslipId;

final readonly class RemovePayslipFromRunHandler
{
    public function __construct(
        private PayrollRunRepository $runs,
        private PayslipRepository $payslips,
    ) {}

    public function __invoke(RemovePayslipFromRun $command): void
    {
        $run = $this->runs->get(PayrollRunId::fromString($command->runId));
        $run->assertAcceptsChanges();

        $this->payslips->delete(PayslipId::fromString($command->payslipId));
    }
}
