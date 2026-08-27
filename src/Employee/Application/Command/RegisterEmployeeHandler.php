<?php

declare(strict_types=1);

namespace HR\Employee\Application\Command;

use Carbon\CarbonImmutable;
use HR\Employee\Domain\Entity\Employee;
use HR\Employee\Domain\Exception\DuplicateEmployeeNumber;
use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\BankAccount;
use HR\Employee\Domain\ValueObject\Compensation;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Employee\Domain\ValueObject\EmployeeNumber;
use HR\Employee\Domain\ValueObject\PayFrequency;
use HR\Employee\Domain\ValueObject\PersonName;
use HR\Employee\Domain\ValueObject\StatutoryProfile;
use HR\Identity\Domain\Enum\Role;
use HR\Identity\Domain\Service\TemporaryPassword;
use HR\Identity\Infrastructure\Persistence\Eloquent\User;
use HR\Shared\Application\TransactionManager;
use HR\Shared\Domain\ValueObject\Money;

final readonly class RegisterEmployeeHandler
{
    public function __construct(
        private EmployeeRepository $employees,
        private TransactionManager $transaction,
    ) {}

    public function __invoke(RegisterEmployee $command): RegisteredEmployee
    {
        $data = $command->data;
        $employeeNumber = new EmployeeNumber($data->employeeNumber);

        if ($this->employees->employeeNumberExists($employeeNumber)) {
            throw DuplicateEmployeeNumber::of($employeeNumber);
        }

        return $this->transaction->transactional(function () use ($data, $employeeNumber): RegisteredEmployee {
            $userId = null;
            $temporaryPassword = null;

            if ($data->createLoginAccount) {
                // Generated here and returned once for HR to pass on. Only its
                // hash is stored; the plain value never touches the database.
                $temporaryPassword = TemporaryPassword::generate();

                $user = User::query()->create([
                    'name' => $data->name,
                    'email' => strtolower($data->workEmail),
                    'password' => $temporaryPassword,
                    'role' => $data->role ? Role::from($data->role) : Role::Employee,
                    'is_active' => true,
                ]);

                $userId = $user->id;
            }

            $employee = Employee::register(
                employeeNumber: $employeeNumber,
                name: new PersonName($data->name),
                workEmail: $data->workEmail,
                joinedOn: CarbonImmutable::parse($data->joinedOn),
                jobTitle: $data->jobTitle,
                compensation: new Compensation(
                    basicSalary: Money::fromDecimal($data->basicSalary),
                    frequency: PayFrequency::Monthly,
                    fixedAllowance: $data->fixedAllowance !== null
                        ? Money::fromDecimal($data->fixedAllowance)
                        : null,
                ),
                statutoryProfile: new StatutoryProfile(
                    dateOfBirth: CarbonImmutable::parse($data->dateOfBirth),
                    isCitizen: $data->isCitizen,
                    epfApplicable: $data->epfApplicable,
                    socsoApplicable: $data->socsoApplicable,
                    eisApplicable: $data->eisApplicable,
                    epfNumber: $data->epfNumber,
                    socsoNumber: $data->socsoNumber,
                    taxReferenceNumber: $data->taxReferenceNumber,
                    nationalIdNumber: $data->nationalIdNumber,
                ),
                department: $data->department,
                bankAccount: $data->bankName && $data->bankAccountNumber
                    ? new BankAccount($data->bankName, $data->bankAccountNumber, $data->name)
                    : null,
                reportsTo: $data->reportsTo ? EmployeeId::fromString($data->reportsTo) : null,
                userId: $userId,
            );

            $this->employees->save($employee);

            return new RegisteredEmployee($employee, $temporaryPassword);
        });
    }
}
