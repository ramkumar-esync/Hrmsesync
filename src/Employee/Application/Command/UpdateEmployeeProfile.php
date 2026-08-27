<?php

declare(strict_types=1);

namespace HR\Employee\Application\Command;

/**
 * Edit an existing employee's profile details. Salary lives in its own command
 * (UpdateCompensation) and status in ChangeEmploymentStatus, so this covers the
 * remaining editable fields: identity, role, and bank account.
 *
 * Any field left null is unchanged, so a partial edit only touches what the
 * form actually sent.
 */
final readonly class UpdateEmployeeProfile
{
    public function __construct(
        public string $employeeId,
        public ?string $name = null,
        public ?string $jobTitle = null,
        public ?string $department = null,
        public ?string $bankName = null,
        public ?string $bankAccountNumber = null,
    ) {}
}
