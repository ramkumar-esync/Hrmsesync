<?php

declare(strict_types=1);

namespace HR\Payroll\Domain\ValueObject;

use HR\Shared\Domain\Exception\InvariantViolation;
use HR\Shared\Domain\ValueObject\Money;

/**
 * One line on a payslip. Amounts are always positive; whether the line adds to
 * or subtracts from pay is decided by the type, not by the sign.
 */
final readonly class PayComponent implements \JsonSerializable
{
    public function __construct(
        public PayComponentType $type,
        public Money $amount,
        public ?string $description = null,
        public bool $systemGenerated = false,
    ) {
        if ($amount->isNegative()) {
            throw InvariantViolation::because(
                "A pay component cannot be negative. Use the matching deduction type instead of a negative {$type->value}."
            );
        }
    }

    public static function earning(PayComponentType $type, Money $amount, ?string $description = null): self
    {
        if (! $type->isEarning()) {
            throw InvariantViolation::because("{$type->value} is a deduction, not an earning.");
        }

        return new self($type, $amount, $description);
    }

    public static function deduction(PayComponentType $type, Money $amount, ?string $description = null, bool $systemGenerated = false): self
    {
        if ($type->isEarning()) {
            throw InvariantViolation::because("{$type->value} is an earning, not a deduction.");
        }

        return new self($type, $amount, $description, $systemGenerated);
    }

    public function label(): string
    {
        return $this->description ?? $this->type->label();
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'label' => $this->label(),
            'amount' => $this->amount,
            'system_generated' => $this->systemGenerated,
        ];
    }
}
