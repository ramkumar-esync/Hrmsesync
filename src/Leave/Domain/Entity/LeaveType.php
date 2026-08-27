<?php

declare(strict_types=1);

namespace HR\Leave\Domain\Entity;

use HR\Leave\Domain\ValueObject\AccrualPolicy;
use HR\Leave\Domain\ValueObject\LeaveTypeId;
use HR\Shared\Domain\Exception\InvariantViolation;

/**
 * Annual leave, sick leave, unpaid leave, and so on.
 *
 * The rules that vary between organisations live here as data rather than as
 * branches in the application code.
 */
final class LeaveType
{
    private function __construct(
        public readonly LeaveTypeId $id,
        private string $code,
        private string $name,
        private bool $paid,
        private AccrualPolicy $accrualPolicy,
        private float $defaultEntitlementDays,
        private float $carryForwardCap,
        private int $carryForwardExpiryMonths,
        private bool $allowHalfDay,
        private bool $requiresAttachment,
        private ?int $maxConsecutiveDays,
        private int $minNoticeDays,
        private bool $allowBackdating,
        private bool $active,
    ) {}

    public static function define(
        string $code,
        string $name,
        bool $paid = true,
        AccrualPolicy $accrualPolicy = AccrualPolicy::AnnualGrant,
        float $defaultEntitlementDays = 0.0,
        float $carryForwardCap = 0.0,
        int $carryForwardExpiryMonths = 3,
        bool $allowHalfDay = true,
        bool $requiresAttachment = false,
        ?int $maxConsecutiveDays = null,
        int $minNoticeDays = 0,
        bool $allowBackdating = false,
        ?LeaveTypeId $id = null,
    ): self {
        if (! preg_match('/^[A-Z][A-Z0-9_]{1,19}$/', $code)) {
            throw InvariantViolation::because(
                'A leave type code must be 2–20 uppercase letters, digits or underscores.'
            );
        }

        if ($defaultEntitlementDays < 0 || $carryForwardCap < 0) {
            throw InvariantViolation::because('Leave day settings cannot be negative.');
        }

        return new self(
            id: $id ?? LeaveTypeId::generate(),
            code: $code,
            name: $name,
            paid: $paid,
            accrualPolicy: $accrualPolicy,
            defaultEntitlementDays: $defaultEntitlementDays,
            carryForwardCap: $carryForwardCap,
            carryForwardExpiryMonths: $carryForwardExpiryMonths,
            allowHalfDay: $allowHalfDay,
            requiresAttachment: $requiresAttachment,
            maxConsecutiveDays: $maxConsecutiveDays,
            minNoticeDays: $minNoticeDays,
            allowBackdating: $allowBackdating,
            active: true,
        );
    }

    public static function reconstitute(
        LeaveTypeId $id,
        string $code,
        string $name,
        bool $paid,
        AccrualPolicy $accrualPolicy,
        float $defaultEntitlementDays,
        float $carryForwardCap,
        int $carryForwardExpiryMonths,
        bool $allowHalfDay,
        bool $requiresAttachment,
        ?int $maxConsecutiveDays,
        int $minNoticeDays,
        bool $allowBackdating,
        bool $active,
    ): self {
        return new self(
            $id, $code, $name, $paid, $accrualPolicy, $defaultEntitlementDays,
            $carryForwardCap, $carryForwardExpiryMonths, $allowHalfDay,
            $requiresAttachment, $maxConsecutiveDays, $minNoticeDays,
            $allowBackdating, $active,
        );
    }

    /** How many days carry into next year, after applying the cap. */
    public function carryForwardFrom(float $unusedDays): float
    {
        if ($this->carryForwardCap <= 0) {
            return 0.0;
        }

        return round(min(max($unusedDays, 0.0), $this->carryForwardCap), 2);
    }

    public function tracksBalance(): bool
    {
        return $this->accrualPolicy !== AccrualPolicy::Unlimited;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isPaid(): bool
    {
        return $this->paid;
    }

    public function accrualPolicy(): AccrualPolicy
    {
        return $this->accrualPolicy;
    }

    public function defaultEntitlementDays(): float
    {
        return $this->defaultEntitlementDays;
    }

    public function carryForwardCap(): float
    {
        return $this->carryForwardCap;
    }

    public function carryForwardExpiryMonths(): int
    {
        return $this->carryForwardExpiryMonths;
    }

    public function allowsHalfDay(): bool
    {
        return $this->allowHalfDay;
    }

    public function requiresAttachment(): bool
    {
        return $this->requiresAttachment;
    }

    public function maxConsecutiveDays(): ?int
    {
        return $this->maxConsecutiveDays;
    }

    public function minNoticeDays(): int
    {
        return $this->minNoticeDays;
    }

    public function allowsBackdating(): bool
    {
        return $this->allowBackdating;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
