<?php

declare(strict_types=1);

namespace HR\Payroll\Application\Command;

/**
 * HR entering one employee's pay for the run.
 *
 * @param list<array{type: string, amount: string, description?: string|null}> $earnings
 * @param list<array{type: string, amount: string, description?: string|null}> $deductions
 */
final readonly class RecordPayrollEntry
{
    public function __construct(
        public string $runId,
        public string $employeeId,
        public array $earnings = [],
        public array $deductions = [],
        public ?string $remarks = null,
        public bool $useContractualSalary = true,
    ) {}
}
