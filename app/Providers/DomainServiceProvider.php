<?php

declare(strict_types=1);

namespace App\Providers;

use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Infrastructure\Persistence\Eloquent\EloquentEmployeeRepository;
use HR\Leave\Application\Command\ApplyForLeaveHandler;
use HR\Leave\Domain\Repository\LeaveApplicationRepository;
use HR\Leave\Domain\Repository\LeaveEntitlementRepository;
use HR\Leave\Domain\Repository\LeaveTypeRepository;
use HR\Leave\Domain\Service\HolidayCalendar;
use HR\Leave\Domain\Service\WorkingDayCalculator;
use HR\Leave\Infrastructure\Calendar\CalendarWorkingDayCalculator;
use HR\Leave\Infrastructure\Calendar\DatabaseHolidayCalendar;
use HR\Leave\Infrastructure\Persistence\Eloquent\EloquentLeaveApplicationRepository;
use HR\Leave\Infrastructure\Persistence\Eloquent\EloquentLeaveEntitlementRepository;
use HR\Leave\Infrastructure\Persistence\Eloquent\EloquentLeaveTypeRepository;
use HR\Payroll\Domain\Event\PayslipIssued;
use HR\Payroll\Domain\Repository\PayrollRunRepository;
use HR\Payroll\Domain\Repository\PayslipRepository;
use HR\Payroll\Domain\Service\IncomeTaxCalculator;
use HR\Payroll\Domain\Service\PayslipCalculator;
use HR\Payroll\Domain\Service\PayslipRenderer;
use HR\Payroll\Domain\Service\StatutoryContributionCalculator;
use HR\Payroll\Domain\Service\UnpaidLeaveLedger;
use HR\Payroll\Infrastructure\Leave\LeaveContextUnpaidLeaveLedger;
use HR\Payroll\Infrastructure\Listener\GeneratePayslipDocument;
use HR\Payroll\Infrastructure\Pdf\DomPdfPayslipRenderer;
use HR\Payroll\Infrastructure\Persistence\Eloquent\EloquentPayrollRunRepository;
use HR\Payroll\Infrastructure\Persistence\Eloquent\EloquentPayslipRepository;
use HR\Payroll\Infrastructure\Statutory\MalaysianStatutoryCalculator;
use HR\Payroll\Infrastructure\Statutory\ManualIncomeTaxCalculator;
use HR\Payroll\Infrastructure\Statutory\NoStatutoryCalculator;
use HR\Payroll\Infrastructure\Statutory\ProgressiveEstimateIncomeTaxCalculator;
use HR\Shared\Application\EventPublisher;
use HR\Shared\Application\TransactionManager;
use HR\Shared\Domain\Contract\Clock;
use HR\Shared\Infrastructure\DatabaseTransactionManager;
use HR\Shared\Infrastructure\LaravelEventPublisher;
use HR\Shared\Infrastructure\SystemClock;
use HR\Attendance\Domain\Repository\AttendanceSheetRepository;
use HR\Attendance\Domain\Service\LeaveVerifier;
use HR\Attendance\Infrastructure\Persistence\Eloquent\EloquentAttendanceSheetRepository;
use HR\Attendance\Infrastructure\Leave\ApprovedLeaveVerifier;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Where the domain's ports get their adapters.
 *
 * Everything above this file depends on interfaces. This is the one place that
 * knows which concrete implementation is in play, which is what makes it
 * possible to swap the statutory engine, the PDF renderer or the persistence
 * layer without touching a single domain class.
 */
final class DomainServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        // Shared kernel
        Clock::class => SystemClock::class,
        TransactionManager::class => DatabaseTransactionManager::class,
        EventPublisher::class => LaravelEventPublisher::class,

        // Repositories: domain port → Eloquent adapter
        EmployeeRepository::class => EloquentEmployeeRepository::class,
        PayrollRunRepository::class => EloquentPayrollRunRepository::class,
        PayslipRepository::class => EloquentPayslipRepository::class,
        LeaveTypeRepository::class => EloquentLeaveTypeRepository::class,
        LeaveEntitlementRepository::class => EloquentLeaveEntitlementRepository::class,
        LeaveApplicationRepository::class => EloquentLeaveApplicationRepository::class,

        // Attendance
        AttendanceSheetRepository::class => EloquentAttendanceSheetRepository::class,
        LeaveVerifier::class => ApprovedLeaveVerifier::class,

        // Cross-context anti-corruption layer
        UnpaidLeaveLedger::class => LeaveContextUnpaidLeaveLedger::class,
    ];

    public function register(): void
    {
        $this->registerStatutoryEngines();
        $this->registerPayslipServices();
        $this->registerLeaveServices();
    }

    public function boot(): void
    {
        Event::listen(PayslipIssued::class, [GeneratePayslipDocument::class, 'handle']);
    }

    private function registerStatutoryEngines(): void
    {
        $this->app->singleton(StatutoryContributionCalculator::class, function ($app) {
            $profile = (string) config('payroll.statutory_profile', 'malaysia');

            return match ($profile) {
                'malaysia' => new MalaysianStatutoryCalculator(
                    config: (array) config('statutory.malaysia', []),
                    currency: (string) config('payroll.currency', 'MYR'),
                ),
                default => new NoStatutoryCalculator,
            };
        });

        $this->app->singleton(IncomeTaxCalculator::class, function ($app) {
            return match ((string) config('payroll.income_tax_engine', 'manual')) {
                'progressive_estimate' => new ProgressiveEstimateIncomeTaxCalculator(
                    (array) config('statutory.malaysia.pcb_estimate', []),
                ),
                // Manual is the default and the safe choice: see the class docblock.
                default => new ManualIncomeTaxCalculator,
            };
        });
    }

    private function registerPayslipServices(): void
    {
        $this->app->singleton(PayslipCalculator::class, fn ($app) => new PayslipCalculator(
            statutory: $app->make(StatutoryContributionCalculator::class),
            tax: $app->make(IncomeTaxCalculator::class),
            unpaidLeave: $app->make(UnpaidLeaveLedger::class),
            workingDaysPerMonth: (int) config('payroll.working_days_per_month', 22),
        ));

        $this->app->singleton(PayslipRenderer::class, fn ($app) => new DomPdfPayslipRenderer(
            filesystem: $app->make(FilesystemFactory::class),
            disk: (string) config('payroll.payslips.disk', 'local'),
            directory: (string) config('payroll.payslips.path', 'payslips'),
            companyName: (string) config('payroll.company.name'),
            companyRegistrationNumber: config('payroll.company.registration_number'),
            companyAddress: config('payroll.company.address'),
            logoPath: config('payroll.company.logo_path'),
            logoHeightMm: (float) config('payroll.company.logo_height_mm', 12),
        ));
    }

    private function registerLeaveServices(): void
    {
        $this->app->singleton(HolidayCalendar::class, fn ($app) => new DatabaseHolidayCalendar(
            database: $app->make('db'),
            cache: $app->make('cache.store'),
            region: config('leave.holiday_region'),
        ));

        $this->app->singleton(WorkingDayCalculator::class, fn ($app) => new CalendarWorkingDayCalculator(
            holidays: $app->make(HolidayCalendar::class),
            restDays: (array) config('leave.rest_days', [6, 7]),
        ));

        $this->app->bind(ApplyForLeaveHandler::class, fn ($app) => new ApplyForLeaveHandler(
            leaveTypes: $app->make(LeaveTypeRepository::class),
            entitlements: $app->make(LeaveEntitlementRepository::class),
            applications: $app->make(LeaveApplicationRepository::class),
            workingDays: $app->make(WorkingDayCalculator::class),
            transaction: $app->make(TransactionManager::class),
            events: $app->make(EventPublisher::class),
            clock: $app->make(Clock::class),
            backdateGraceDays: (int) config('leave.backdate_grace_days', 7),
        ));
    }
}
