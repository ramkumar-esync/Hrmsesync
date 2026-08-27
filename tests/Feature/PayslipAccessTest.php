<?php

declare(strict_types=1);

namespace Tests\Feature;

use HR\Identity\Domain\Enum\Role;
use HR\Identity\Infrastructure\Persistence\Eloquent\User;
use HR\Payroll\Domain\Entity\Payslip;
use HR\Payroll\Domain\ValueObject\EmployeeSnapshot;
use HR\Payroll\Domain\ValueObject\PayComponent;
use HR\Payroll\Domain\ValueObject\PayComponentType;
use HR\Payroll\Domain\ValueObject\PayPeriod;
use HR\Payroll\Domain\ValueObject\PayrollRunId;
use HR\Payroll\Presentation\Policy\PayslipPolicy;
use HR\Shared\Domain\ValueObject\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Salary is the most sensitive data in the system. These tests exist to make it
 * hard to accidentally widen access to it.
 */
final class PayslipAccessTest extends TestCase
{
    use RefreshDatabase;

    private function payslipFor(string $employeeId, bool $issued = true): Payslip
    {
        $payslip = Payslip::draft(
            runId: PayrollRunId::generate(),
            period: PayPeriod::fromString('2026-07'),
            employee: new EmployeeSnapshot(
                employeeId: $employeeId,
                employeeNumber: 'EMP-0001',
                name: 'Test Person',
                jobTitle: 'Engineer',
                department: null,
                epfNumber: null,
                socsoNumber: null,
                taxReferenceNumber: null,
                bankName: null,
                bankAccountMasked: null,
            ),
            earnings: [
                PayComponent::earning(PayComponentType::BasicSalary, Money::fromDecimal('5000.00')),
            ],
        );

        if ($issued) {
            $payslip->issue(now()->toImmutable());
        }

        return $payslip;
    }

    private function userWithEmployee(Role $role, string $employeeId): User
    {
        $user = new User([
            'name' => 'Test',
            'email' => $role->value.'@example.com',
            'password' => 'secret-for-testing-only',
            'role' => $role,
            'is_active' => true,
        ]);
        $user->id = (string) \Illuminate\Support\Str::uuid7();

        // Stand in for the employee relation without touching the database.
        $user->setRelation('employee', (object) ['id' => $employeeId]);

        return $user;
    }

    public function test_an_employee_can_view_their_own_issued_payslip(): void
    {
        $employeeId = (string) \Illuminate\Support\Str::uuid7();
        $user = $this->userWithEmployee(Role::Employee, $employeeId);

        $this->assertTrue((new PayslipPolicy)->view($user, $this->payslipFor($employeeId)));
    }

    public function test_an_employee_cannot_view_someone_elses_payslip(): void
    {
        $user = $this->userWithEmployee(Role::Employee, (string) \Illuminate\Support\Str::uuid7());
        $otherPayslip = $this->payslipFor((string) \Illuminate\Support\Str::uuid7());

        $this->assertFalse((new PayslipPolicy)->view($user, $otherPayslip));
    }

    public function test_an_employee_cannot_view_a_draft_of_their_own_payslip(): void
    {
        $employeeId = (string) \Illuminate\Support\Str::uuid7();
        $user = $this->userWithEmployee(Role::Employee, $employeeId);

        $this->assertFalse(
            (new PayslipPolicy)->view($user, $this->payslipFor($employeeId, issued: false))
        );
    }

    public function test_a_manager_has_no_special_access_to_salary_data(): void
    {
        // Approving leave does not imply seeing pay.
        $user = $this->userWithEmployee(Role::Manager, (string) \Illuminate\Support\Str::uuid7());
        $reportPayslip = $this->payslipFor((string) \Illuminate\Support\Str::uuid7());

        $this->assertFalse((new PayslipPolicy)->view($user, $reportPayslip));
    }

    public function test_hr_can_view_any_payslip(): void
    {
        $user = $this->userWithEmployee(Role::HrAdmin, (string) \Illuminate\Support\Str::uuid7());

        $this->assertTrue(
            (new PayslipPolicy)->view($user, $this->payslipFor((string) \Illuminate\Support\Str::uuid7()))
        );
    }

    public function test_payslip_endpoints_reject_unauthenticated_callers(): void
    {
        $this->getJson('/api/me/payslips')->assertUnauthorized();
        $this->getJson('/api/payslips/'.\Illuminate\Support\Str::uuid7())->assertUnauthorized();
    }
}
