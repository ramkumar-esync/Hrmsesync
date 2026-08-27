<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The domain layer deals in CarbonImmutable throughout. Making it the
        // framework default removes a whole class of "why did this date change"
        // bugs at the boundary.
        Date::use(\Carbon\CarbonImmutable::class);

        // Fail loudly in development instead of silently returning null for a
        // relation nobody eager-loaded.
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        // The payslip template ships with the Payroll context, not in resources/.
        $this->loadViewsFrom(
            base_path('src/Payroll/Presentation/Views'),
            'payroll',
        );
    }
}
