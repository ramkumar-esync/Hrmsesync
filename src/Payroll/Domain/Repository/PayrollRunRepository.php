<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Repository;

use HR\Payroll\Domain\Entity\PayrollRun;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Payroll\Domain\ValueObject\PayrollRunId;

interface PayrollRunRepository
{
    public function save(PayrollRun $run): void;

    public function find(PayrollRunId $id): ?PayrollRun;

    /** @throws \HR\Payroll\Domain\Exception\PayrollRunNotFound */
    public function get(PayrollRunId $id): PayrollRun;

    public function findByPeriod(PayPeriod $period): ?PayrollRun;

    public function existsForPeriod(PayPeriod $period): bool;
}
