<?php

declare(strict_types=1);

namespace HR\Payroll\Application\Command;

use Carbon\CarbonImmutable;
use HR\Payroll\Domain\Entity\PayrollRun;
use HR\Payroll\Domain\Exception\DuplicatePayrollRun;
use HR\Payroll\Domain\Repository\PayrollRunRepository;
use HR\Payroll\Domain\ValueObject\PayPeriod;

final readonly class OpenPayrollRunHandler
{
    public function __construct(private PayrollRunRepository $runs) {}

    public function __invoke(OpenPayrollRun $command): PayrollRun
    {
        $period = PayPeriod::fromString($command->period);

        if ($this->runs->existsForPeriod($period)) {
            throw DuplicatePayrollRun::forPeriod($period);
        }

        $run = PayrollRun::open(
            period: $period,
            paymentDate: CarbonImmutable::parse($command->paymentDate),
            openedBy: $command->openedBy,
            notes: $command->notes,
        );

        $this->runs->save($run);

        return $run;
    }
}
