<?php

declare(strict_types=1);

namespace HR\Identity\Domain\Enum;

enum Role: string
{
    /** Sees only their own payslips and leave. */
    case Employee = 'employee';

    /** An employee who also approves leave for their direct reports. */
    case Manager = 'manager';

    /** Runs payroll, maintains employee records, approves leave for anyone. */
    case HrAdmin = 'hr_admin';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Employee',
            self::Manager => 'Manager',
            self::HrAdmin => 'HR administrator',
        };
    }

    public function canRunPayroll(): bool
    {
        return $this === self::HrAdmin;
    }

    public function canApproveLeaveForAnyone(): bool
    {
        return $this === self::HrAdmin;
    }

    public function canApproveLeave(): bool
    {
        return $this === self::HrAdmin || $this === self::Manager;
    }
}
