<?php

declare(strict_types=1);

namespace App\Providers;

use HR\Leave\Domain\Entity\LeaveApplication;
use HR\Leave\Presentation\Policy\LeaveApplicationPolicy;
use HR\Payroll\Domain\Entity\Payslip;
use HR\Payroll\Presentation\Policy\PayslipPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Policies are registered by hand because the subjects are domain entities, not
 * Eloquent models — Laravel's convention-based discovery does not find them.
 */
final class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Payslip::class, PayslipPolicy::class);
        Gate::policy(LeaveApplication::class, LeaveApplicationPolicy::class);
    }
}
