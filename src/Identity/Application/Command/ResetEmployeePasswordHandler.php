<?php

declare(strict_types=1);

namespace HR\Identity\Application\Command;

use HR\Employee\Domain\Repository\EmployeeRepository;
use HR\Employee\Domain\ValueObject\EmployeeId;
use HR\Identity\Domain\Service\TemporaryPassword;
use HR\Identity\Infrastructure\Persistence\Eloquent\User;
use HR\Shared\Domain\Exception\EntityNotFound;

final readonly class ResetEmployeePasswordHandler
{
    public function __construct(
        private EmployeeRepository $employees,
    ) {}

    /**
     * @return string the new temporary password, to be shown once
     */
    public function __invoke(ResetEmployeePassword $command): string
    {
        $employee = $this->employees->get(EmployeeId::fromString($command->employeeId));

        $userId = $employee->userId();
        $user = $userId !== null ? User::query()->find($userId) : null;

        if ($user === null) {
            throw new EntityNotFound('This employee has no login account to reset.');
        }

        $password = TemporaryPassword::generate();

        // Casts to 'hashed' on the model, so only the hash is written. Revoking
        // existing tokens forces a fresh sign-in with the new password.
        $user->password = $password;
        $user->save();
        $user->tokens()->delete();

        return $password;
    }
}
