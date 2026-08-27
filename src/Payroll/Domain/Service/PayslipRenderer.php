<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Service;

use HR\Payroll\Domain\Entity\Payslip;

/** Port for turning an issued payslip into a stored PDF. */
interface PayslipRenderer
{
    /** @return string Path to the stored document on the private disk. */
    public function render(Payslip $payslip): string;
}
