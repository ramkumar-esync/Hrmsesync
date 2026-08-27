<?php

declare(strict_types=1);

namespace HR\Payroll\Application\Command;

use Carbon\CarbonImmutable;
use HR\Payroll\Domain\Entity\PayrollRun;
use HR\Payroll\Domain\Repository\PayrollRunRepository;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Shared\Domain\Contract\Clock;

final readonly class MarkPayrollRunPaidHandler
{
    public function __construct(private PayrollRunRepository $runs, private Clock $clock) {}

    public function __invoke(MarkPayrollRunPaid $command): PayrollRun
    {
        $run = $this->runs->get(PayrollRunId::fromString($command->runId));

        $run->markAsPaid($command->paidAt !== null
            ? CarbonImmutable::parse($command->paidAt)
            : $this->clock->now());

        $this->runs->save($run);

        return $run;
    }
}
