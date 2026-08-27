<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\ValueObject;

use HR\Shared\Domain\ValueObject\Money;

/**
 * What the employer pays on top of gross wages. It never touches net pay, but
 * it must appear on the payslip and in the statutory submission files.
 */
final readonly class EmployerContribution implements \JsonSerializable
{
    public function __construct(
        public PayComponentType $type,
        public Money $amount,
        public ?string $description = null,
    ) {}

    public function label(): string
    {
        return $this->description ?? $this->type->label().' (employer)';
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'label' => $this->label(),
            'amount' => $this->amount,
        ];
    }
}
