<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\ValueObject;

enum PayComponentType: string
{
    // Earnings
    case BasicSalary = 'basic_salary';
    case FixedAllowance = 'fixed_allowance';
    case Overtime = 'overtime';
    case Bonus = 'bonus';
    case Commission = 'commission';
    case Claim = 'claim';
    case OtherEarning = 'other_earning';

    // Deductions
    case Epf = 'epf';
    case Socso = 'socso';
    case Eis = 'eis';
    case Pcb = 'pcb';
    case UnpaidLeave = 'unpaid_leave';
    case Advance = 'advance';
    case OtherDeduction = 'other_deduction';

    public function isEarning(): bool
    {
        return in_array($this, [
            self::BasicSalary, self::FixedAllowance, self::Overtime,
            self::Bonus, self::Commission, self::Claim, self::OtherEarning,
        ], true);
    }

    public function isStatutory(): bool
    {
        return in_array($this, [self::Epf, self::Socso, self::Eis, self::Pcb], true);
    }

    /**
     * Whether the component counts toward the wage base used for EPF/SOCSO/EIS.
     *
     * Reimbursed claims are not wages; overtime and bonuses generally are. Check
     * this mapping against your own payroll policy before going live.
     */
    public function countsTowardStatutoryWages(): bool
    {
        return $this->isEarning() && $this !== self::Claim;
    }

    public function label(): string
    {
        return str_replace('_', ' ', ucfirst($this->value));
    }
}
