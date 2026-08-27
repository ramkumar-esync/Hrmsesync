<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\Service;

use HR\Payroll\Domain\ValueObject\EmployerContribution;
use HR\Payroll\Domain\ValueObject\PayComponent;

final readonly class StatutoryResult
{
    /**
     * @param  list<PayComponent>  $employeeDeductions
     * @param  list<EmployerContribution>  $employerContributions
     */
    public function __construct(
        public array $employeeDeductions = [],
        public array $employerContributions = [],
    ) {}

    public static function none(): self
    {
        return new self();
    }
}
