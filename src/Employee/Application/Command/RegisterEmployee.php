<?php

declare(strict_types=1);

namespace HR\Employee\Application\Command;

use HR\Employee\Application\DTO\RegisterEmployeeData;

final readonly class RegisterEmployee
{
    public function __construct(public RegisterEmployeeData $data) {}
}
