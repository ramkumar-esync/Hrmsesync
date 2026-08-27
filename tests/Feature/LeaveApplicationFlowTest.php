<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use HR\Leave\Application\Command\ApplyForLeave;
use HR\Leave\Application\Command\ApplyForLeaveHandler;
use HR\Leave\Application\Command\DecideLeaveApplication;
use HR\Leave\Application\Command\DecideLeaveApplicationHandler;
use HR\Leave\Application\Command\GrantAnnualEntitlements;
use HR\Leave\Application\Command\GrantAnnualEntitlementsHandler;
use HR\Leave\Domain\Exception\OverlappingLeave;
use HR\Leave\Domain\Repository\LeaveEntitlementRepository;
use HR\Leave\Domain\Repository\LeaveTypeRepository;
use HR\Leave\Domain\ValueObject\LeaveStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Database\Seeders\LeaveTypeSeeder;

/** End-to-end: apply, approve, and watch the balance move. */
final class LeaveApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $employeeId;

    private string $managerId;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-07-01 09:00:00');

        $this->seed(LeaveTypeSeeder::class);
        [$this->employeeId, $this->managerId] = $this->createEmployees();

        app(GrantAnnualEntitlementsHandler::class)(new GrantAnnualEntitlements(2026));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @return array{0: string, 1: string} */
    private function createEmployees(): array
    {
        // Insert directly: this test is about the leave domain, not registration.
        $manager = (string) \Illuminate\Support\Str::uuid7();
        $employee = (string) \Illuminate\Support\Str::uuid7();

        foreach ([[$manager, 'EMP-9001', 'Manager Person', null], [$employee, 'EMP-9002', 'Staff Person', $manager]] as [$id, $number, $name, $reportsTo]) {
            \Illuminate\Support\Facades\DB::table('employees')->insert([
                'id' => $id,
                'employee_number' => $number,
                'name' => $name,
                'work_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                'joined_on' => '2024-01-15',
                'status' => 'confirmed',
                'job_title' => 'Tester',
                'currency' => 'MYR',
                'basic_salary_minor' => 500000,
                'pay_frequency' => 'monthly',
                'date_of_birth' => '1990-01-01',
                'reports_to' => $reportsTo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$employee, $manager];
    }

    private function annualLeaveTypeId(): string
    {
        return app(LeaveTypeRepository::class)->findByCode('ANNUAL')->id->value;
    }

    private function availableDays(): float
    {
        $entitlement = app(LeaveEntitlementRepository::class)->findFor(
            $this->employeeId,
            \HR\Leave\Domain\ValueObject\LeaveTypeId::fromString($this->annualLeaveTypeId()),
            2026,
        );

        return $entitlement->balance()->available();
    }

    public function test_applying_reserves_days_and_approving_consumes_them(): void
    {
        $before = $this->availableDays();

        // Monday 13 July to Wednesday 15 July 2026 — three working days.
        $application = app(ApplyForLeaveHandler::class)(new ApplyForLeave(
            employeeId: $this->employeeId,
            leaveTypeId: $this->annualLeaveTypeId(),
            startDate: '2026-07-13',
            endDate: '2026-07-15',
            reason: 'Family trip',
        ));

        $this->assertSame(LeaveStatus::Pending, $application->status());
        $this->assertSame(3.0, $application->workingDays());
        $this->assertSame($before - 3.0, $this->availableDays(), 'Pending days must be held immediately.');

        app(DecideLeaveApplicationHandler::class)(new DecideLeaveApplication(
            applicationId: $application->id->value,
            approverEmployeeId: $this->managerId,
            approve: true,
        ));

        $this->assertSame($before - 3.0, $this->availableDays(), 'Approval must not double-count.');
    }

    public function test_rejecting_returns_the_reserved_days(): void
    {
        $before = $this->availableDays();

        $application = app(ApplyForLeaveHandler::class)(new ApplyForLeave(
            employeeId: $this->employeeId,
            leaveTypeId: $this->annualLeaveTypeId(),
            startDate: '2026-07-13',
            endDate: '2026-07-14',
            reason: 'Personal',
        ));

        app(DecideLeaveApplicationHandler::class)(new DecideLeaveApplication(
            applicationId: $application->id->value,
            approverEmployeeId: $this->managerId,
            approve: false,
            note: 'Clashes with the release.',
        ));

        $this->assertSame($before, $this->availableDays());
    }

    public function test_weekends_are_not_charged_against_the_balance(): void
    {
        // Friday 17 July to Monday 20 July: two working days, two rest days.
        $application = app(ApplyForLeaveHandler::class)(new ApplyForLeave(
            employeeId: $this->employeeId,
            leaveTypeId: $this->annualLeaveTypeId(),
            startDate: '2026-07-17',
            endDate: '2026-07-20',
            reason: 'Long weekend',
        ));

        $this->assertSame(2.0, $application->workingDays());
        $this->assertCount(4, $application->days(), 'All calendar days are recorded, chargeable or not.');
    }

    public function test_a_half_day_costs_half_a_day(): void
    {
        $application = app(ApplyForLeaveHandler::class)(new ApplyForLeave(
            employeeId: $this->employeeId,
            leaveTypeId: $this->annualLeaveTypeId(),
            startDate: '2026-07-13',
            endDate: '2026-07-13',
            reason: 'Appointment',
            startPortion: 'first_half',
            endPortion: 'first_half',
        ));

        $this->assertSame(0.5, $application->workingDays());
    }

    public function test_overlapping_applications_are_refused(): void
    {
        app(ApplyForLeaveHandler::class)(new ApplyForLeave(
            employeeId: $this->employeeId,
            leaveTypeId: $this->annualLeaveTypeId(),
            startDate: '2026-07-13',
            endDate: '2026-07-15',
            reason: 'First request',
        ));

        $this->expectException(OverlappingLeave::class);

        app(ApplyForLeaveHandler::class)(new ApplyForLeave(
            employeeId: $this->employeeId,
            leaveTypeId: $this->annualLeaveTypeId(),
            startDate: '2026-07-15',
            endDate: '2026-07-16',
            reason: 'Overlapping request',
        ));
    }
}
