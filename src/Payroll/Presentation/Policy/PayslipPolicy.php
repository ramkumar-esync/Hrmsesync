<?php

declare(strict_types=1);

namespace HR\Payroll\Presentation\Policy;

use HR\Payroll\Domain\Entity\Payslip;
use HR\Payroll\Domain\ValueObject\PayslipStatus;
use HR\Identity\Domain\Enum\Role;
use HR\Identity\Infrastructure\Persistence\Eloquent\User;

/**
 * The single gate protecting salary data.
 *
 * Every payslip read goes through here. An employee sees their own issued
 * payslips and nothing else — not a colleague's, and not their own drafts,
 * which may still change before the run is finalised.
 */
final class PayslipPolicy
{
    public function view(User $user, Payslip $payslip): bool
    {
        if ($user->role === Role::HrAdmin) {
            return true;
        }

        return $payslip->employee()->employeeId === $user->employeeId()
            && $payslip->status() !== PayslipStatus::Draft;
    }

    public function download(User $user, Payslip $payslip): bool
    {
        if (! $this->view($user, $payslip)) {
            return false;
        }

        // A draft has no document, and HR should not be handing one out.
        return $payslip->status()->isVisibleToEmployee() || $user->role === Role::HrAdmin;
    }

    public function manage(User $user): bool
    {
        return $user->role === Role::HrAdmin;
    }
}
