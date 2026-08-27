<?php

declare(strict_types=1);

namespace HR\Employee\Application\Command;

use HR\Employee\Domain\Entity\Employee;

/**
 * The outcome of registering an employee.
 *
 * When a login account is created, the temporary password is generated
 * server-side and returned here exactly once — it is shown to the HR user on
 * screen and never stored in readable form. If no login was created, or on any
 * later read of the employee, temporaryPassword is null.
 */
final readonly class RegisteredEmployee
{
    public function __construct(
        public Employee $employee,
        public ?string $temporaryPassword = null,
    ) {}
}
