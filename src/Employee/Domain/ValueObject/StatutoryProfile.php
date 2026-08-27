<?php

declare(strict_types=1);

namespace HR\Employee\Domain\ValueObject;

use Carbon\CarbonImmutable;

/**
 * The facts about a person that change how statutory contributions are worked
 * out. Kept separate from identity so the payroll engine depends on the
 * attributes it actually needs, not on the whole employee record.
 */
final readonly class StatutoryProfile
{
    public function __construct(
        public CarbonImmutable $dateOfBirth,
        public bool $isCitizen = true,
        public bool $epfApplicable = true,
        public bool $socsoApplicable = true,
        public bool $eisApplicable = true,
        public ?string $epfNumber = null,
        public ?string $socsoNumber = null,
        public ?string $taxReferenceNumber = null,
        public ?string $nationalIdNumber = null,
        public int $taxDependants = 0,
        public bool $isMarried = false,
    ) {}

    public function ageOn(CarbonImmutable $date): int
    {
        return (int) $this->dateOfBirth->diffInYears($date);
    }
}
