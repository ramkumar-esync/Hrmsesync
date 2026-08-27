<?php

declare(strict_types=1);

namespace HR\Employee\Domain\Entity;

use Carbon\CarbonImmutable;
use HR\Employee\Domain\ValueObject\BankAccount;
use HR\Employee\Domain\ValueObject\Compensation;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Employee\Domain\ValueObject\EmployeeNumber;
use HR\Employee\Domain\ValueObject\EmploymentStatus;
use HR\Employee\Domain\ValueObject\PersonName;
use HR\Employee\Domain\ValueObject\StatutoryProfile;
use HR\Shared\Domain\Event\RecordsDomainEvents;
use HR\Shared\Domain\Exception\InvariantViolation;

/**
 * Aggregate root for a member of staff.
 *
 * Plain PHP — no Eloquent, no framework. Persistence happens through
 * EmployeeRepository, which maps this object to and from a database row.
 */
final class Employee
{
    use RecordsDomainEvents;

    private function __construct(
        public readonly EmployeeId $id,
        private EmployeeNumber $employeeNumber,
        private PersonName $name,
        private string $workEmail,
        private CarbonImmutable $joinedOn,
        private ?CarbonImmutable $leftOn,
        private EmploymentStatus $status,
        private string $jobTitle,
        private ?string $department,
        private Compensation $compensation,
        private StatutoryProfile $statutoryProfile,
        private ?BankAccount $bankAccount,
        private ?EmployeeId $reportsTo,
        private ?string $userId,
    ) {}

    public static function register(
        EmployeeNumber $employeeNumber,
        PersonName $name,
        string $workEmail,
        CarbonImmutable $joinedOn,
        string $jobTitle,
        Compensation $compensation,
        StatutoryProfile $statutoryProfile,
        ?string $department = null,
        ?BankAccount $bankAccount = null,
        ?EmployeeId $reportsTo = null,
        ?string $userId = null,
        EmploymentStatus $status = EmploymentStatus::Probation,
        ?EmployeeId $id = null,
    ): self {
        if (! filter_var($workEmail, FILTER_VALIDATE_EMAIL)) {
            throw InvariantViolation::because("\"{$workEmail}\" is not a valid email address.");
        }

        if ($statutoryProfile->dateOfBirth->greaterThan($joinedOn)) {
            throw InvariantViolation::because('Date of birth cannot fall after the join date.');
        }

        return new self(
            id: $id ?? EmployeeId::generate(),
            employeeNumber: $employeeNumber,
            name: $name,
            workEmail: strtolower($workEmail),
            joinedOn: $joinedOn->startOfDay(),
            leftOn: null,
            status: $status,
            jobTitle: $jobTitle,
            department: $department,
            compensation: $compensation,
            statutoryProfile: $statutoryProfile,
            bankAccount: $bankAccount,
            reportsTo: $reportsTo,
            userId: $userId,
        );
    }

    /** Rebuilds the aggregate from storage without re-running creation rules. */
    public static function reconstitute(
        EmployeeId $id,
        EmployeeNumber $employeeNumber,
        PersonName $name,
        string $workEmail,
        CarbonImmutable $joinedOn,
        ?CarbonImmutable $leftOn,
        EmploymentStatus $status,
        string $jobTitle,
        ?string $department,
        Compensation $compensation,
        StatutoryProfile $statutoryProfile,
        ?BankAccount $bankAccount,
        ?EmployeeId $reportsTo,
        ?string $userId,
    ): self {
        return new self(
            $id, $employeeNumber, $name, $workEmail, $joinedOn, $leftOn, $status,
            $jobTitle, $department, $compensation, $statutoryProfile, $bankAccount,
            $reportsTo, $userId,
        );
    }

    public function changeCompensation(Compensation $compensation): void
    {
        $this->assertActive('change compensation');

        $this->compensation = $compensation;
    }

    public function confirm(): void
    {
        if ($this->status !== EmploymentStatus::Probation) {
            throw InvariantViolation::because('Only an employee on probation can be confirmed.');
        }

        $this->status = EmploymentStatus::Confirmed;
    }

    /**
     * Move the employee to a new employment status.
     *
     * This is the general path HR uses from the directory. It covers the moves
     * between active states (probation → confirmed, or onto a contract) and the
     * moves that end employment (resigned, terminated). Ending employment needs
     * a last day and is better done through terminate(), which records it; this
     * method routes there so a single call site cannot leave an ex-employee
     * with no leaving date.
     */
    public function changeStatus(EmploymentStatus $status, ?CarbonImmutable $effectiveOn = null): void
    {
        if ($status === $this->status) {
            return;
        }

        if (! $status->isActive()) {
            $this->terminate($effectiveOn ?? CarbonImmutable::now(), $status);

            return;
        }

        // Moving back to an active status: only meaningful from another active
        // status (e.g. probation ↔ contract ↔ confirmed). Re-hiring someone who
        // has left is a separate, deliberate act, not a status toggle.
        if (! $this->status->isActive()) {
            throw InvariantViolation::because(
                'This employee has left. Re-register them to bring them back on.',
            );
        }

        $this->status = $status;
        $this->leftOn = null;
    }

    public function terminate(CarbonImmutable $lastDay, EmploymentStatus $reason = EmploymentStatus::Resigned): void
    {
        if (! $this->status->isActive()) {
            throw InvariantViolation::because('This employee has already left.');
        }

        if ($lastDay->lessThan($this->joinedOn)) {
            throw InvariantViolation::because('The last day cannot fall before the join date.');
        }

        $this->status = $reason;
        $this->leftOn = $lastDay->startOfDay();
    }

    public function assignManager(?EmployeeId $managerId): void
    {
        if ($managerId !== null && $managerId->equals($this->id)) {
            throw InvariantViolation::because('An employee cannot report to themselves.');
        }

        $this->reportsTo = $managerId;
    }

    public function linkUserAccount(string $userId): void
    {
        $this->userId = $userId;
    }

    public function updateBankAccount(BankAccount $bankAccount): void
    {
        $this->bankAccount = $bankAccount;
    }

    public function updateProfile(PersonName $name, string $jobTitle, ?string $department): void
    {
        $this->name = $name;
        $this->jobTitle = $jobTitle;
        $this->department = $department;
    }

    /** Was this person on the payroll at any point during the given window? */
    public function wasEmployedDuring(CarbonImmutable $start, CarbonImmutable $end): bool
    {
        if ($this->joinedOn->greaterThan($end)) {
            return false;
        }

        return $this->leftOn === null || $this->leftOn->greaterThanOrEqualTo($start);
    }

    public function employeeNumber(): EmployeeNumber
    {
        return $this->employeeNumber;
    }

    public function name(): PersonName
    {
        return $this->name;
    }

    public function workEmail(): string
    {
        return $this->workEmail;
    }

    public function joinedOn(): CarbonImmutable
    {
        return $this->joinedOn;
    }

    public function leftOn(): ?CarbonImmutable
    {
        return $this->leftOn;
    }

    public function status(): EmploymentStatus
    {
        return $this->status;
    }

    public function jobTitle(): string
    {
        return $this->jobTitle;
    }

    public function department(): ?string
    {
        return $this->department;
    }

    public function compensation(): Compensation
    {
        return $this->compensation;
    }

    public function statutoryProfile(): StatutoryProfile
    {
        return $this->statutoryProfile;
    }

    public function bankAccount(): ?BankAccount
    {
        return $this->bankAccount;
    }

    public function reportsTo(): ?EmployeeId
    {
        return $this->reportsTo;
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    private function assertActive(string $action): void
    {
        if (! $this->status->isActive()) {
            throw InvariantViolation::because(
                "Cannot {$action} for an employee who has left the company."
            );
        }
    }
}
