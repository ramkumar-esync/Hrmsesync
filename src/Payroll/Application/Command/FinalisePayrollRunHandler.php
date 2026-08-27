<?php

declare(strict_types=1);

namespace HR\Payroll\Application\Command;

use HR\Payroll\Domain\Entity\PayrollRun;
use HR\Payroll\Domain\Repository\PayrollRunRepository;
use HR\Payroll\Domain\Repository\PayslipRepository;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Shared\Application\EventPublisher;
use HR\Shared\Application\TransactionManager;
use HR\Shared\Domain\Contract\Clock;
use HR\Shared\Domain\ValueObject\Money;

/**
 * Locks the run and issues every payslip in it.
 *
 * This is the point of no return: after it, payslips are visible to employees,
 * PDFs are generated, and nothing in the run can be edited. Any correction has
 * to be a new payslip that supersedes the original.
 */
final readonly class FinalisePayrollRunHandler
{
    public function __construct(
        private PayrollRunRepository $runs,
        private PayslipRepository $payslips,
        private TransactionManager $transaction,
        private EventPublisher $events,
        private Clock $clock,
    ) {}

    public function __invoke(FinalisePayrollRun $command): PayrollRun
    {
        return $this->transaction->transactional(function () use ($command): PayrollRun {
            $run = $this->runs->get(PayrollRunId::fromString($command->runId));
            $run->assertAcceptsChanges();

            $payslips = $this->payslips->forRun($run->id);
            $now = $this->clock->now();

            $netTotal = Money::zero();
            $costTotal = Money::zero();
            $pending = [];

            foreach ($payslips as $payslip) {
                $payslip->issue($now);

                $netTotal = $netTotal->add($payslip->netPay());
                $costTotal = $costTotal->add($payslip->employerCost());

                $this->payslips->save($payslip);
                $pending[] = $payslip->releaseEvents();
            }

            $run->finalise(
                payslipCount: count($payslips),
                totalNetPay: $netTotal,
                totalEmployerCost: $costTotal,
                finalisedBy: $command->finalisedBy,
                finalisedAt: $now,
            );

            $this->runs->save($run);

            $this->events->publishAll(array_merge($run->releaseEvents(), ...$pending));

            return $run;
        });
    }
}
