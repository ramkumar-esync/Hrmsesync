<?php

declare(strict_types=1);

namespace HR\Identity\Application\Command;

/**
 * An HR admin or a manager resets an employee's login password.
 *
 * The new password is generated server-side and returned once so it can be
 * passed on. Authorisation — HR resets anyone, a manager only their own direct
 * reports — is enforced by policy at the controller, not here.
 */
final readonly class ResetEmployeePassword
{
    public function __construct(
        public string $employeeId,
    ) {}
}
