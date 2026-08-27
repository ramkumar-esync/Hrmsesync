<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use HR\Employee\Domain\Entity\Employee;
use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\BankAccount;
use HR\Employee\Domain\ValueObject\Compensation;
use HR\Employee\Domain\ValueObject\EmployeeNumber;
use HR\Employee\Domain\ValueObject\EmploymentStatus;
use HR\Employee\Domain\ValueObject\PersonName;
use HR\Employee\Domain\ValueObject\StatutoryProfile;
use HR\Identity\Domain\Enum\Role;
use HR\Identity\Infrastructure\Persistence\Eloquent\User;
use HR\Leave\Application\Command\GrantAnnualEntitlements;
use HR\Leave\Application\Command\GrantAnnualEntitlementsHandler;
use HR\Shared\Domain\ValueObject\Money;
use Illuminate\Database\Seeder;

/** A small org chart so the portal is usable the moment it is installed. */
final class DemoDataSeeder extends Seeder
{
    public function run(
        EmployeeRepository $employees,
        GrantAnnualEntitlementsHandler $grantEntitlements,
    ): void {
        $people = [
            [
                'number' => 'EMP-0001', 'name' => 'Sai Anand',
                'email' => 'sanand@esync.com.my', 'title' => 'Manager',
                'department' => 'People', 'salary' => '9500.00', 'allowance' => '600.00',
                'dob' => '1988-04-12', 'joined' => '2019-02-01', 'role' => Role::Manager,
                'manager' => null,
            ],
            [
                'number' => 'EMP-0002', 'name' => 'Farhana',
                'email' => 'farhanna@esync.com.my', 'title' => 'HR Manager',
                'department' => 'People', 'salary' => '9500.00', 'allowance' => '600.00',
                'dob' => '1988-04-12', 'joined' => '2019-02-01', 'role' => Role::HrAdmin,
                'manager' => null,
            ],
            [
                'number' => 'EMP-0003', 'name' => 'Shaarmini Nair',
                'email' => 'shaarmini.nair@esync.com.my', 'title' => 'Manager',
                'department' => 'People', 'salary' => '12000.00', 'allowance' => '800.00',
                'dob' => '1985-11-03', 'joined' => '2018-07-15', 'role' => Role::Manager,
                'manager' => null,
            ],
            [
                'number' => 'EMP-0004', 'name' => 'Ram kumar',
                'email' => 'ramkumar.mood@esync.com.my', 'title' => 'Software Engineer',
                'department' => 'Engineering', 'salary' => '6800.00', 'allowance' => '400.00',
                'dob' => '1995-06-22', 'joined' => '2023-03-06', 'role' => Role::Employee,
                'manager' => 'EMP-0002',
            ], 
            [
                'number' => 'EMP-0005', 'name' => 'Rajesh Kumar',
                'email' => 'rajesh.kumar@esync.com.my', 'title' => 'Software Engineer',
                'department' => 'Finance', 'salary' => '4200.00', 'allowance' => '250.00',
                'dob' => '1993-08-17', 'joined' => '2022-01-10', 'role' => Role::Employee,
                'manager' => 'EMP-0001',
            ],
        ];

        $created = [];

        foreach ($people as $index => $person) {
            $number = new EmployeeNumber($person['number']);

            if ($employees->findByEmployeeNumber($number) !== null) {
                continue;
            }

            $user = User::query()->create([
                'name' => $person['name'],
                'email' => $person['email'],
                // Demo credential. Change it before this touches a real network.
                'password' => 'Esync@2026',
                'role' => $person['role'],
                'is_active' => true,
            ]);

            $employee = Employee::register(
                employeeNumber: $number,
                name: new PersonName($person['name']),
                workEmail: $person['email'],
                joinedOn: CarbonImmutable::parse($person['joined']),
                jobTitle: $person['title'],
                compensation: new Compensation(
                    basicSalary: Money::fromDecimal($person['salary']),
                    fixedAllowance: Money::fromDecimal($person['allowance']),
                ),
                statutoryProfile: new StatutoryProfile(
                    dateOfBirth: CarbonImmutable::parse($person['dob']),
                    isCitizen: true,
                    epfNumber: '1'.str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT),
                    socsoNumber: 'S'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                    taxReferenceNumber: 'SG'.str_pad((string) ($index + 1), 9, '0', STR_PAD_LEFT),
                ),
                department: $person['department'],
                bankAccount: new BankAccount(
                    'Maybank',
                    '5140'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                    $person['name'],
                ),
                userId: $user->id,
                status: EmploymentStatus::Confirmed,
            );

            $employees->save($employee);
            $created[$person['number']] = $employee;
        }

        // Second pass so managers exist before their reports point at them.
        foreach ($people as $person) {
            if ($person['manager'] === null || ! isset($created[$person['number']])) {
                continue;
            }

            $manager = $created[$person['manager']] ?? null;

            if ($manager === null) {
                continue;
            }

            $employee = $created[$person['number']];
            $employee->assignManager($manager->id);
            $employees->save($employee);
        }

        $granted = $grantEntitlements(new GrantAnnualEntitlements((int) now()->year));

        $this->command?->info("Created ".count($created)." demo employees and {$granted} leave entitlements.");
        $this->command?->warn('Every demo account uses the password "password". Change or remove them.');
    }
}
