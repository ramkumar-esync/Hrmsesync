<?php

declare(strict_types=1);

namespace HR\Leave\Presentation\Policy;

use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Identity\Domain\Enum\Role;
use HR\Identity\Infrastructure\Persistence\Eloquent\User;
use HR\Leave\Domain\Entity\LeaveApplication;

/**
 * Who may see and decide a leave request.
 *
 * HR sees everything. A manager sees their own team. Everyone else sees only
 * their own requests — leave reasons are often medical or personal.
 */
final readonly class LeaveApplicationPolicy
{
    public function __construct(private EmployeeRepository $employees) {}

    public function view(User $user, LeaveApplication $application): bool
    {
        if ($user->role === Role::HrAdmin) {
            return true;
        }

        if ($application->employeeId === $user->employeeId()) {
            return true;
        }

        return $this->managesApplicant($user, $application);
    }

    public function decide(User $user, LeaveApplication $application): bool
    {
        // Nobody approves their own leave, whatever their role.
        if ($application->employeeId === $user->employeeId()) {
            return false;
        }

        if ($user->role === Role::HrAdmin) {
            return true;
        }

        return $user->role === Role::Manager && $this->managesApplicant($user, $application);
    }

    public function cancel(User $user, LeaveApplication $application): bool
    {
        return $user->role === Role::HrAdmin
            || $application->employeeId === $user->employeeId();
    }

    private function managesApplicant(User $user, LeaveApplication $application): bool
    {
        $employeeId = $user->employeeId();

        if ($employeeId === null) {
            return false;
        }

        $applicant = $this->employees->find(EmployeeId::fromString($application->employeeId));

        return $applicant?->reportsTo()?->value === $employeeId;
    }
}
