<?php

declare(strict_types=1);

namespace HR\Employee\Domain\ValueObject;

use HR\Shared\Domain\Exception\InvariantViolation;

final readonly class BankAccount
{
    public function __construct(
        public string $bankName,
        public string $accountNumber,
        public ?string $accountHolder = null,
    ) {
        if (trim($bankName) === '' || ! preg_match('/^\d{6,20}$/', $accountNumber)) {
            throw InvariantViolation::because(
                'A bank account needs a bank name and a 6–20 digit account number.'
            );
        }
    }

    /** Only the last four digits ever reach a payslip or an API response. */
    public function masked(): string
    {
        return str_repeat('•', max(0, strlen($this->accountNumber) - 4))
            .substr($this->accountNumber, -4);
    }
}
